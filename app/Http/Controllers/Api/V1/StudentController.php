<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\StudentParent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class StudentController extends Controller
{
    /**
     * GET /api/v1/students
     */
    public function index(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $query = Student::where('school_id', $schoolId)
            ->with(['schoolClass', 'section', 'parents' => fn($q) => $q->where('is_primary', true)]);

        if ($request->class_id)   $query->where('class_id',   $request->class_id);
        if ($request->section_id) $query->where('section_id', $request->section_id);
        if ($request->status)     $query->where('status',     $request->status);
        if ($request->search) {
            $s = $request->search;
            $query->where(fn($q) =>
                $q->where('name', 'like', "%$s%")
                  ->orWhere('admission_no', 'like', "%$s%")
            );
        }

        $perPage  = min((int)$request->input('per_page', 20), 100);
        $students = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $students->map(fn($s) => $this->formatStudent($s)),
            'meta'    => [
                'total'    => $students->total(),
                'page'     => $students->currentPage(),
                'per_page' => $students->perPage(),
                'pages'    => $students->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/students
     */
    public function store(Request $request): JsonResponse
    {
        $schoolId     = $request->user()->school_id;
        $subscription = Subscription::where('school_id', $schoolId)->first();

        if ($subscription && $subscription->isBlocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription has expired. Please contact Vikashana to reactivate.',
                'blocked' => true,
            ], 403);
        }

        $validated = $request->validate([
            'admission_no'     => 'required|string|unique:students',
            'name'             => 'required|string|max:150',
            'dob'              => 'nullable|date',
            'gender'           => 'nullable|in:Male,Female,Other',
            'blood_group'      => 'nullable|string|max:5',
            'address'          => 'nullable|string',
            'city'             => 'nullable|string|max:100',
            'state'            => 'nullable|string|max:100',
            'pincode'          => 'nullable|string|max:10',
            'aadhar_no'        => 'nullable|string|max:20',
            'previous_school'  => 'nullable|string|max:200',
            'status'           => 'nullable|in:Active,Inactive,Transferred,Graduated',
            'admission_date'   => 'nullable|date',
            'class_id'         => 'required|exists:classes,id',
            'section_id'       => 'required|exists:sections,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'photo'            => 'nullable|image|max:2048',
            'documents.*'      => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            // Parent fields
            'parent_name'      => 'required|string',
            'parent_phone'     => 'required|string|max:20',
            'parent_email'     => 'nullable|email',
            'parent_relation'  => 'nullable|string',
        ], [
            'photo.max'            => 'Photo must be under 2 MB.',
            'photo.image'          => 'Photo must be a valid image (jpg, png, gif, etc.).',
            'documents.*.max'      => 'Each document must be under 5 MB.',
            'documents.*.mimes'    => 'Documents must be PDF, Word, or image files.',
        ]);

        $credentials = [];

        $student = DB::transaction(function () use ($validated, $request, &$credentials) {
            $schoolId = $request->user()->school_id;

            // Handle photo upload
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('students/photos', 'public');
            }

            // Build address JSON
            $addressJson = json_encode([
                'street'          => $validated['address']         ?? null,
                'city'            => $validated['city']            ?? null,
                'state'           => $validated['state']           ?? null,
                'pincode'         => $validated['pincode']         ?? null,
                'aadhar_no'       => $validated['aadhar_no']       ?? null,
                'previous_school' => $validated['previous_school'] ?? null,
            ]);

            $s = Student::create([
                'school_id'        => $schoolId,
                'admission_no'     => $validated['admission_no'],
                'name'             => $validated['name'],
                'dob'              => $validated['dob'] ?? null,
                'gender'           => $validated['gender'] ?? null,
                'blood_group'      => $validated['blood_group'] ?? null,
                'address'          => $addressJson,
                'photo'            => $photoPath,
                'status'           => $validated['status'] ?? 'Active',
                'admission_date'   => $validated['admission_date'] ?? now()->toDateString(),
                'class_id'         => $validated['class_id'],
                'section_id'       => $validated['section_id'],
                'academic_year_id' => $validated['academic_year_id'],
            ]);

            // Handle document uploads
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $doc) {
                    $docPath = $doc->store('students/documents', 'public');
                    $s->documents()->create([
                        'name' => $doc->getClientOriginalName(),
                        'path' => $docPath,
                        'type' => $doc->getMimeType(),
                        'size' => $doc->getSize(),
                    ]);
                }
            }

            // Child DOB as initial password for parent (ddmmyyyy)
            $dobPassword = $s->dob ? $s->dob->format('dmY') : '01011990';

            // Check if parent phone already has an account (same school, re-use for siblings)
            $existingParent = User::where('phone', $validated['parent_phone'])
                ->where('school_id', $schoolId)
                ->where('role', 'parent')
                ->first();

            if ($existingParent) {
                // Parent exists — link student only, NEVER update password
                $parentUser  = $existingParent;
                $credentials = [
                    'username'      => $validated['parent_phone'],
                    'temp_password' => '(unchanged — use existing password)',
                    'note'          => 'Existing parent account. Password was set on first registration and has not been changed.',
                ];
            } else {
                $parentUser = User::create([
                    'school_id'     => $schoolId,
                    'name'          => $validated['parent_name'],
                    'email'         => null,
                    'phone'         => $validated['parent_phone'],
                    'password'      => $dobPassword,
                    'plain_password'=> encrypt($dobPassword),
                    'role'          => 'parent',
                    'status'        => 'active',
                ]);
                $credentials = [
                    'username'      => $validated['parent_phone'],
                    'temp_password' => $dobPassword,
                    'note'          => 'Parent logs in with mobile number. Initial password is child\'s DOB (ddmmyyyy). Password will never change.',
                ];
            }

            $s->parents()->create([
                'name'       => $validated['parent_name'],
                'phone'      => $validated['parent_phone'],
                'email'      => $validated['parent_email'] ?? null,
                'relation'   => $validated['parent_relation'] ?? 'Father',
                'is_primary' => true,
                'user_id'    => $parentUser->id,
            ]);

            return $s->load(['schoolClass', 'section', 'parents', 'documents']);
        });

        ActivityLog::log(
            $request->user()->id, $request->user()->school_id,
            'create', 'students',
            "Added student: {$student->name} ({$student->admission_no})",
            '👨‍🎓'
        );

        return response()->json([
            'success'     => true,
            'message'     => 'Student added successfully',
            'data'        => $this->formatStudent($student, true),
            'credentials' => $credentials,
        ], 201);
    }

    /**
     * GET /api/v1/students/{id}
     */
    public function show(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudentAccess($request, $student);

        $student->load(['schoolClass', 'section', 'parents', 'documents', 'academicYear']);

        return response()->json([
            'success' => true,
            'data'    => $this->formatStudent($student, true),
        ]);
    }

    /**
     * PUT /api/v1/students/{id}
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        $this->authorizeSchool($request, $student->school_id);

        $validated = $request->validate([
            'name'            => 'sometimes|string|max:150',
            'dob'             => 'nullable|date',
            'gender'          => 'nullable|in:Male,Female,Other',
            'blood_group'     => 'nullable|string|max:5',
            'address'         => 'nullable|string',
            'city'            => 'nullable|string|max:100',
            'state'           => 'nullable|string|max:100',
            'pincode'         => 'nullable|string|max:10',
            'aadhar_no'       => 'nullable|string|max:20',
            'previous_school' => 'nullable|string|max:200',
            'status'          => 'nullable|in:Active,Inactive,Transferred,Graduated',
            'class_id'        => 'sometimes|exists:classes,id',
            'section_id'      => 'sometimes|exists:sections,id',
            'photo'           => 'nullable|image|max:2048',
            'documents.*'     => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'parent_name'     => 'sometimes|string',
            'parent_phone'    => 'sometimes|string|max:20',
            'parent_email'    => 'nullable|email',
            'parent_relation' => 'nullable|string',
        ], [
            'photo.max'         => 'Photo must be under 2 MB.',
            'photo.image'       => 'Photo must be a valid image (jpg, png, gif, etc.).',
            'documents.*.max'   => 'Each document must be under 5 MB.',
            'documents.*.mimes' => 'Documents must be PDF, Word, or image files.',
        ]);

        // Handle photo upload
        if ($request->hasFile('photo')) {
            if ($student->photo) Storage::disk('public')->delete($student->photo);
            $validated['photo'] = $request->file('photo')->store('students/photos', 'public');
        }

        // Merge address sub-fields into JSON
        $existingAddr = [];
        if ($student->address) {
            $decoded = json_decode($student->address, true);
            $existingAddr = is_array($decoded) ? $decoded : ['street' => $student->address];
        }
        $validated['address'] = json_encode([
            'street'          => $validated['address']         ?? $existingAddr['street']          ?? null,
            'city'            => $validated['city']            ?? $existingAddr['city']             ?? null,
            'state'           => $validated['state']           ?? $existingAddr['state']            ?? null,
            'pincode'         => $validated['pincode']         ?? $existingAddr['pincode']          ?? null,
            'aadhar_no'       => $validated['aadhar_no']       ?? $existingAddr['aadhar_no']        ?? null,
            'previous_school' => $validated['previous_school'] ?? $existingAddr['previous_school']  ?? null,
        ]);

        // Handle document uploads (append, not replace)
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $doc) {
                $docPath = $doc->store('students/documents', 'public');
                $student->documents()->create([
                    'name' => $doc->getClientOriginalName(),
                    'path' => $docPath,
                    'type' => $doc->getMimeType(),
                    'size' => $doc->getSize(),
                ]);
            }
        }

        // Update primary parent record (or create if none exists)
        $parentFields = array_intersect_key($validated, array_flip(['parent_name', 'parent_phone', 'parent_email', 'parent_relation']));
        if (!empty($parentFields)) {
            $primary = $student->parents()->where('is_primary', true)->first()
                    ?? $student->parents()->first();
            if ($primary) {
                $primary->update([
                    'name'     => $parentFields['parent_name']     ?? $primary->name,
                    'phone'    => $parentFields['parent_phone']    ?? $primary->phone,
                    'email'    => array_key_exists('parent_email', $parentFields) ? $parentFields['parent_email'] : $primary->email,
                    'relation' => $parentFields['parent_relation'] ?? $primary->relation,
                ]);
            } else {
                // No parent record yet (e.g. student converted from admission enquiry) — create one
                $student->parents()->create([
                    'name'       => $parentFields['parent_name']     ?? '',
                    'phone'      => $parentFields['parent_phone']    ?? '',
                    'email'      => $parentFields['parent_email']    ?? null,
                    'relation'   => $parentFields['parent_relation'] ?? 'Guardian',
                    'is_primary' => true,
                ]);
            }
        }

        // Remove non-student-column keys before updating
        unset($validated['city'], $validated['state'], $validated['pincode'],
              $validated['aadhar_no'], $validated['previous_school'],
              $validated['parent_name'], $validated['parent_phone'],
              $validated['parent_email'], $validated['parent_relation']);

        $student->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Student updated',
            'data'    => $this->formatStudent($student->fresh(['schoolClass', 'section', 'parents', 'documents'])),
        ]);
    }

    /**
     * DELETE /api/v1/students/{id}
     */
    public function destroy(Request $request, Student $student): JsonResponse
    {
        $this->authorizeSchool($request, $student->school_id);
        ActivityLog::log(
            $request->user()->id, $student->school_id,
            'delete', 'students',
            "Deleted student: {$student->name} ({$student->admission_no})",
            '🗑️'
        );
        $student->delete();

        return response()->json(['success' => true, 'message' => 'Student deleted']);
    }

    /**
     * GET /api/v1/students/{id}/attendance
     */
    public function attendance(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudentAccess($request, $student);

        $month = $request->input('month', now()->format('Y-m'));

        $records = $student->attendance()
            ->whereRaw("DATE_FORMAT(date,'%Y-%m') = ?", [$month])
            ->orderBy('date')
            ->get(['date','status','note']);

        $total   = $records->count();
        $present = $records->whereIn('status',['Present','Late'])->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'month'      => $month,
                'records'    => $records,
                'summary'    => [
                    'total'      => $total,
                    'present'    => $present,
                    'absent'     => $records->where('status','Absent')->count(),
                    'late'       => $records->where('status','Late')->count(),
                    'percentage' => $total > 0 ? round(($present/$total)*100,1) : 0,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/students/{id}/fees
     */
    public function fees(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudentAccess($request, $student);

        $invoices = $student->feeInvoices()
            ->with('payments')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $invoices->map(fn($i) => [
                'id'         => $i->id,
                'invoice_no' => $i->invoice_no,
                'month'      => $i->month,
                'total'      => $i->total,
                'paid'       => $i->paid,
                'balance'    => $i->balance,
                'status'     => $i->status,
                'due_date'   => $i->due_date?->toDateString(),
                'items'      => $i->items,
                'payments'   => $i->payments,
            ]),
        ]);
    }

    /**
     * GET /api/v1/students/{id}/marks
     */
    public function marks(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudentAccess($request, $student);

        $marks = $student->marks()
            ->with(['exam','examSubject.subject'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $marks->map(fn($m) => [
                'exam'           => $m->exam->name,
                'subject'        => $m->examSubject->subject->name,
                'marks_obtained' => $m->marks_obtained,
                'max_marks'      => $m->examSubject->max_marks,
                'grade'          => $m->grade,
                'result'         => $m->result,
            ]),
        ]);
    }

    /**
     * POST /api/v1/students/{student}/reset-parent-password
     */
    public function resetParentPassword(Request $request, Student $student): JsonResponse
    {
        $this->authorizeSchool($request, $student->school_id);

        $parent = $student->parents()->where('is_primary', true)->first()
               ?? $student->parents()->first();

        if (!$parent?->user_id) {
            return response()->json(['success' => false, 'message' => 'No parent login account found'], 404);
        }

        $dobPassword = $student->dob ? $student->dob->format('dmY') : '01011990';

        User::find($parent->user_id)->update(['password' => $dobPassword]);

        return response()->json([
            'success' => true,
            'message' => 'Parent password reset to child\'s DOB',
            'data'    => ['temp_password' => $dobPassword],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function formatStudent(Student $s, bool $full = false): array
    {
        $primary = $s->relationLoaded('parents')
            ? $s->parents->firstWhere('is_primary', true) ?? $s->parents->first()
            : null;

        // Decode address JSON
        $addr = [];
        if ($s->address) {
            $decoded = json_decode($s->address, true);
            $addr = is_array($decoded) ? $decoded : ['street' => $s->address];
        }

        $data = [
            'id'              => $s->id,
            'admission_no'    => $s->admission_no,
            'name'            => $s->name,
            'dob'             => $s->dob?->toDateString(),
            'gender'          => $s->gender,
            'blood_group'     => $s->blood_group,
            'photo'           => $s->photo ? asset('storage/'.$s->photo) : null,
            'status'          => $s->status,
            'class'           => $s->relationLoaded('schoolClass') ? $s->schoolClass->name : null,
            'section'         => $s->relationLoaded('section')     ? $s->section->name     : null,
            'class_id'        => $s->class_id,
            'section_id'      => $s->section_id,
            'parent_name'     => $primary?->name,
            'parent_phone'    => $primary?->phone,
            'parent_email'    => $primary?->email,
            'parent_relation' => $primary?->relation,
            'address'         => $addr['street']          ?? null,
            'city'            => $addr['city']            ?? null,
            'state'           => $addr['state']           ?? null,
            'pincode'         => $addr['pincode']         ?? null,
            'aadhar_no'       => $addr['aadhar_no']       ?? null,
            'previous_school' => $addr['previous_school'] ?? null,
        ];

        if ($full) {
            $data['admission_date'] = $s->admission_date?->toDateString();
            $data['parents']        = $s->parents ?? [];
            $data['documents']      = $s->relationLoaded('documents')
                ? $s->documents->map(fn($d) => [
                    'id'   => $d->id,
                    'name' => $d->name,
                    'url'  => asset('storage/'.$d->path),
                    'type' => $d->type,
                    'size' => $d->size,
                ])
                : [];

            // Parent login info (phone-based)
            $primaryParent = $s->relationLoaded('parents')
                ? $s->parents->firstWhere('is_primary', true) ?? $s->parents->first()
                : null;
            $parentUser = $primaryParent?->user_id ? User::find($primaryParent->user_id) : null;
            $dobPassword = $s->dob ? $s->dob->format('dmY') : '01011990';
            $data['login'] = [
                'username'      => $primaryParent?->phone ?? '—',
                'temp_password' => $dobPassword,
                'has_login'     => $parentUser !== null,
                'note'          => 'Parent logs in with mobile number. Initial password is child\'s DOB.',
            ];
        }

        return $data;
    }

    private function authorizeSchool(Request $request, int $schoolId): void
    {
        abort_if($request->user()->school_id !== $schoolId, 403, 'Forbidden');
    }

    /**
     * Checks school isolation AND, for parent role, verifies the
     * student belongs to that parent. Admin/teacher/staff pass freely.
     */
    private function authorizeStudentAccess(Request $request, Student $student): void
    {
        // School isolation always applies
        abort_if($request->user()->school_id !== $student->school_id, 403, 'Forbidden');

        $role = $request->user()->role;

        if (in_array($role, ['admin', 'super_admin', 'teacher', 'staff'])) {
            return;
        }

        if ($role === 'parent') {
            $allowed = StudentParent::where('user_id', $request->user()->id)
                ->where('student_id', $student->id)
                ->exists();
            abort_if(!$allowed, 403, 'You can only view your own child\'s data');
            return;
        }

        abort(403, 'Unauthorized');
    }
}
