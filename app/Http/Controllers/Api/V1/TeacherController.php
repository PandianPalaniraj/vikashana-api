<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Subscription;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeacherController extends Controller
{
    // ── Formatter ─────────────────────────────────────────────────────────────

    private function format(Teacher $t): array
    {
        return [
            'id'            => $t->id,
            'empId'         => $t->employee_id ?? '',
            'name'          => $t->name,
            'gender'        => $t->gender ?? '',
            'dob'           => $t->dob?->toDateString() ?? '',
            'bloodGroup'    => $t->blood_group ?? '',
            'phone'         => $t->phone ?? '',
            'email'         => $t->email ?? '',
            'address'       => $t->address ?? '',
            'city'          => $t->city ?? '',
            'state'         => $t->state ?? '',
            'pincode'       => $t->pincode ?? '',
            'designation'   => $t->designation ?? '',
            'qualification' => $t->qualification ?? '',
            'joinDate'      => $t->joining_date?->toDateString() ?? '',
            'status'        => $t->status,
            'subjects'      => $t->subjects_list ?? [],
            'classes'       => $t->classes_list ?? [],
            'sections'      => $t->sections_list ?? [],
            'photo'         => $t->photo,
            'docs'          => $t->docs ?? [],
            'user_id'       => $t->user_id,
        ];
    }

    // ── GET /teachers ─────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $perPage  = min((int) $request->input('per_page', 20), 100);

        $query = Teacher::where('school_id', $schoolId)->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn($sq) =>
                $sq->where('name', 'like', "%{$q}%")
                   ->orWhere('employee_id', 'like', "%{$q}%")
                   ->orWhere('phone', 'like', "%{$q}%")
            );
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => collect($paginated->items())->map(fn($t) => $this->format($t)),
            'meta'    => [
                'page'      => $paginated->currentPage(),
                'total'     => $paginated->total(),
                'per_page'  => $paginated->perPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    // ── POST /teachers ────────────────────────────────────────────────────────

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

        $request->validate([
            'name'          => 'required|string|max:100',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:150',
            'designation'   => 'nullable|string|max:100',
            'qualification' => 'nullable|string|max:100',
            'joinDate'      => 'nullable|date',
            'dob'           => 'nullable|date',
            'gender'        => 'nullable|in:Male,Female,Other',
            'bloodGroup'    => 'nullable|string|max:10',
            'address'       => 'nullable|string|max:500',
            'city'          => 'nullable|string|max:80',
            'state'         => 'nullable|string|max:80',
            'pincode'       => 'nullable|string|max:10',
            'empId'         => 'nullable|string|max:50',
            'subjects'      => 'nullable|array',
            'classes'       => 'nullable|array',
            'sections'      => 'nullable|array',
            'status'        => 'nullable|in:Active,Inactive,On Leave,Resigned',
            'password'      => 'nullable|string|min:6|max:50',
        ]);

        $teacher = Teacher::create([
            'school_id'     => $schoolId,
            'employee_id'   => $request->empId,
            'name'          => $request->name,
            'phone'         => $request->phone,
            'email'         => $request->email,
            'designation'   => $request->designation,
            'qualification' => $request->qualification,
            'joining_date'  => $request->joinDate,
            'dob'           => $request->dob,
            'gender'        => $request->gender,
            'blood_group'   => $request->bloodGroup,
            'address'       => $request->address,
            'city'          => $request->city,
            'state'         => $request->state,
            'pincode'       => $request->pincode,
            'status'        => $request->input('status', 'Active'),
            'subjects_list' => $request->input('subjects', []),
            'classes_list'  => $request->input('classes', []),
            'sections_list' => $request->input('sections', []),
            'photo'         => $request->input('photo'),
            'docs'          => $request->input('docs', []),
        ]);

        // ── Create login account ─────────────────────────────────────────────
        $credentials = null;

        if ($request->phone) {
            // DOB as initial password (ddmmyyyy), fallback if DOB not provided
            $dobPassword = $request->dob
                ? Carbon::parse($request->dob)->format('dmY')
                : 'Teacher@123';

            $user = User::create([
                'school_id' => $schoolId,
                'name'      => $request->name,
                'email'     => $request->email ?? null,
                'phone'     => $request->phone,
                'password'  => $dobPassword,
                'role'      => 'teacher',
                'status'    => 'active',
            ]);

            $teacher->update(['user_id' => $user->id]);

            $credentials = [
                'username'      => $request->phone,
                'temp_password' => $dobPassword,
                'note'          => 'Teacher logs in with mobile number. Initial password is DOB (ddmmyyyy).',
            ];
        }

        ActivityLog::log(
            $request->user()->id, $schoolId,
            'create', 'teachers',
            "Added teacher: {$teacher->name}",
            '👨‍🏫'
        );

        return response()->json([
            'success'     => true,
            'message'     => "Teacher {$teacher->name} added",
            'data'        => $this->format($teacher->fresh()),
            'credentials' => $credentials,
        ], 201);
    }

    // ── GET /teachers/{id} ────────────────────────────────────────────────────

    public function show(Request $request, Teacher $teacher): JsonResponse
    {
        abort_if($teacher->school_id !== $request->user()->school_id, 403, 'Forbidden');

        return response()->json(['success' => true, 'data' => $this->format($teacher)]);
    }

    // ── PUT /teachers/{id} ────────────────────────────────────────────────────

    public function update(Request $request, Teacher $teacher): JsonResponse
    {
        abort_if($teacher->school_id !== $request->user()->school_id, 403, 'Forbidden');

        $request->validate([
            'name'          => 'sometimes|required|string|max:100',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:150',
            'designation'   => 'nullable|string|max:100',
            'qualification' => 'nullable|string|max:100',
            'joinDate'      => 'nullable|date',
            'dob'           => 'nullable|date',
            'gender'        => 'nullable|in:Male,Female,Other',
            'bloodGroup'    => 'nullable|string|max:10',
            'address'       => 'nullable|string|max:500',
            'city'          => 'nullable|string|max:80',
            'state'         => 'nullable|string|max:80',
            'pincode'       => 'nullable|string|max:10',
            'empId'         => 'nullable|string|max:50',
            'subjects'      => 'nullable|array',
            'classes'       => 'nullable|array',
            'sections'      => 'nullable|array',
            'status'        => 'nullable|in:Active,Inactive,On Leave,Resigned',
        ]);

        $teacher->update(array_filter([
            'employee_id'   => $request->empId,
            'name'          => $request->name,
            'phone'         => $request->phone,
            'email'         => $request->email,
            'designation'   => $request->designation,
            'qualification' => $request->qualification,
            'joining_date'  => $request->joinDate,
            'dob'           => $request->dob,
            'gender'        => $request->gender,
            'blood_group'   => $request->bloodGroup,
            'address'       => $request->address,
            'city'          => $request->city,
            'state'         => $request->state,
            'pincode'       => $request->pincode,
            'status'        => $request->status,
            'subjects_list' => $request->subjects,
            'classes_list'  => $request->classes,
            'sections_list' => $request->sections,
            'photo'         => $request->input('photo', '__KEEP__'),
            'docs'          => $request->docs,
        ], fn($v) => $v !== null && $v !== '__KEEP__'));

        // Handle photo separately (allow null / explicit update)
        if ($request->has('photo')) {
            $teacher->update(['photo' => $request->input('photo')]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Teacher updated',
            'data'    => $this->format($teacher->fresh()),
        ]);
    }

    // ── DELETE /teachers/{id} ─────────────────────────────────────────────────

    public function destroy(Request $request, Teacher $teacher): JsonResponse
    {
        abort_if($teacher->school_id !== $request->user()->school_id, 403, 'Forbidden');

        $teacher->delete();

        return response()->json(['success' => true, 'message' => 'Teacher deleted']);
    }

    // ── GET /teachers/{id}/attendance ─────────────────────────────────────────

    public function attendance(Request $request, Teacher $teacher): JsonResponse
    {
        abort_if($teacher->school_id !== $request->user()->school_id, 403, 'Forbidden');

        $month = $request->input('month', now()->format('Y-m'));
        [$year, $mon] = explode('-', $month);

        $records = TeacherAttendance::where('teacher_id', $teacher->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->get()
            ->keyBy(fn($r) => $r->date->toDateString());

        return response()->json([
            'success' => true,
            'data'    => $records->map(fn($r) => [
                'date'   => $r->date->toDateString(),
                'status' => $r->status,
                'note'   => $r->note,
            ]),
        ]);
    }

    // ── POST /teachers/attendance/bulk ────────────────────────────────────────
    // Called via Route::post('teachers/attendance', ...)  — add route if needed

    public function saveAttendance(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $request->validate([
            'date'             => 'required|date',
            'records'          => 'required|array',
            'records.*.id'     => 'required|exists:teachers,id',
            'records.*.status' => 'required|in:P,A,L',
        ]);

        $statusMap = ['P' => 'Present', 'A' => 'Absent', 'L' => 'On Leave'];
        $date      = $request->date;

        DB::transaction(function () use ($request, $schoolId, $date, $statusMap) {
            foreach ($request->records as $rec) {
                TeacherAttendance::updateOrCreate(
                    ['teacher_id' => $rec['id'], 'date' => $date],
                    [
                        'school_id' => $schoolId,
                        'status'    => $statusMap[$rec['status']] ?? 'Present',
                        'note'      => $rec['note'] ?? null,
                        'marked_by' => $request->user()->id,
                    ]
                );
            }
        });

        return response()->json(['success' => true, 'message' => "Attendance saved for {$date}"]);
    }

    // ── GET /teachers/attendance/report?month=&year= ───────────────────────────

    public function attendanceReport(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $month    = (int) $request->input('month', now()->month);
        $year     = (int) $request->input('year',  now()->year);

        $teachers = Teacher::where('school_id', $schoolId)
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id']);

        $records = TeacherAttendance::where('school_id', $schoolId)
            ->whereMonth('date', $month)
            ->whereYear('date',  $year)
            ->get(['teacher_id', 'status']);

        $grouped = $records->groupBy('teacher_id');

        $data = $teachers->map(function ($teacher) use ($grouped) {
            $rows    = $grouped->get($teacher->id, collect());
            $present = $rows->where('status', 'Present')->count();
            $absent  = $rows->where('status', 'Absent')->count();
            $onLeave = $rows->where('status', 'On Leave')->count();
            $total   = $rows->count();
            return [
                'teacher_id'  => $teacher->id,
                'name'        => $teacher->name,
                'employee_id' => $teacher->employee_id ?? '',
                'total'       => $total,
                'present'     => $present,
                'absent'      => $absent,
                'on_leave'    => $onLeave,
                'percent'     => $total > 0 ? round(($present / $total) * 100, 1) : 0,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── POST /teachers/{teacher}/reset-password ───────────────────────────────

    public function resetPassword(Request $request, Teacher $teacher): JsonResponse
    {
        abort_if($request->user()->school_id !== $teacher->school_id, 403, 'Forbidden');

        if (!$teacher->user_id) {
            return response()->json(['success' => false, 'message' => 'No login account found for this teacher'], 404);
        }

        $dobPassword = $teacher->dob
            ? Carbon::parse($teacher->dob)->format('dmY')
            : 'Teacher@123';

        User::find($teacher->user_id)->update(['password' => $dobPassword]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset to DOB',
            'data'    => ['temp_password' => $dobPassword],
        ]);
    }

    // ── GET /teachers/attendance/daily?from=&to= ──────────────────────────────

    public function attendanceDailyGrid(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $from     = $request->input('from', now()->startOfWeek()->toDateString());
        $to       = $request->input('to',   now()->toDateString());

        $records = TeacherAttendance::where('school_id', $schoolId)
            ->whereBetween('date', [$from, $to])
            ->get(['teacher_id', 'date', 'status']);

        $keyMap = ['Present' => 'P', 'Absent' => 'A', 'On Leave' => 'L', 'Late' => 'P', 'Half Day' => 'P'];

        return response()->json([
            'success' => true,
            'data'    => $records->map(fn($r) => [
                'teacher_id' => $r->teacher_id,
                'date'       => $r->date instanceof \Carbon\Carbon ? $r->date->toDateString() : substr($r->date, 0, 10),
                'status'     => $keyMap[$r->status] ?? 'P',
            ]),
        ]);
    }
}
