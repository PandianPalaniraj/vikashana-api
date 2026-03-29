<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\StudentLeave;
use App\Models\StudentParent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    private function fmt(StudentLeave $leave): array
    {
        return [
            'id'          => $leave->id,
            'leave_type'  => $leave->leave_type,
            'from_date'   => $leave->from_date?->toDateString(),
            'to_date'     => $leave->to_date?->toDateString(),
            'total_days'  => $leave->total_days,
            'reason'      => $leave->reason,
            'status'      => $leave->status,
            'remarks'     => $leave->remarks,
            'applied_at'  => $leave->created_at?->toDateString(),
            'reviewed_at' => $leave->reviewed_at?->toDateString(),
            'student'     => $leave->student ? [
                'id'           => $leave->student->id,
                'name'         => $leave->student->name,
                'admission_no' => $leave->student->admission_no,
                'class'        => $leave->student->schoolClass->name ?? '—',
                'class_id'     => $leave->student->class_id,
                'section'      => $leave->student->section->name  ?? '—',
            ] : null,
            'reviewed_by' => $leave->reviewedBy?->name,
        ];
    }

    // ─── GET /leaves ───────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $schoolId = $user->school_id;

        $query = StudentLeave::where('school_id', $schoolId)
            ->with([
                'student.schoolClass:id,name',
                'student.section:id,name',
                'reviewedBy:id,name',
            ])
            ->orderBy('created_at', 'desc');

        if ($user->role === 'parent') {
            // All student IDs this parent owns
            $ownedIds = StudentParent::where('user_id', $user->id)->pluck('student_id');
            // If a specific student is requested, validate ownership first
            if ($request->filled('student_id')) {
                abort_if(!$ownedIds->contains($request->student_id), 403, 'Forbidden.');
                $query->where('student_id', $request->student_id);
            } else {
                $query->whereIn('student_id', $ownedIds);
            }
        } else {
            if ($request->filled('status'))     $query->where('status',     $request->status);
            if ($request->filled('student_id')) $query->where('student_id', $request->student_id);
            if ($request->filled('class_id')) {
                $query->whereHas('student', fn($q) => $q->where('class_id', $request->class_id));
            }
            if ($request->filled('from')) $query->where('from_date', '>=', $request->from);
            if ($request->filled('to'))   $query->where('to_date',   '<=', $request->to);
            if ($request->filled('search')) {
                $q = $request->search;
                $query->whereHas('student', fn($sq) => $sq->where('name', 'like', "%{$q}%")->orWhere('admission_no', 'like', "%{$q}%"));
            }
        }

        $summary = [];
        if ($user->role !== 'parent') {
            $base = StudentLeave::where('school_id', $schoolId);
            $summary = [
                'total'    => (clone $base)->count(),
                'pending'  => (clone $base)->where('status', 'Pending')->count(),
                'approved' => (clone $base)->where('status', 'Approved')->count(),
                'rejected' => (clone $base)->where('status', 'Rejected')->count(),
            ];
        }

        $perPage = min((int) $request->input('per_page', 20), 200);
        $page    = max(1, (int) $request->input('page', 1));
        $total   = $query->count();
        $items   = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'success' => true,
            'data'    => $items->map(fn($l) => $this->fmt($l))->values(),
            'meta'    => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage))],
            'summary' => $summary,
        ]);
    }

    // ─── POST /leaves ──────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->role !== 'parent', 403, 'Only parents can apply for leave.');

        $data = $request->validate([
            'student_id' => 'required|integer',
            'leave_type' => 'required|in:Medical,Family,Personal,Other',
            'from_date'  => 'required|date|after_or_equal:today',
            'to_date'    => 'required|date|after_or_equal:from_date',
            'reason'     => 'required|string|max:1000',
        ]);

        StudentParent::where('user_id', $user->id)
            ->where('student_id', $data['student_id'])
            ->firstOrFail();

        $leave = StudentLeave::create([
            'school_id'  => $user->school_id,
            'student_id' => $data['student_id'],
            'applied_by' => $user->id,
            'leave_type' => $data['leave_type'],
            'from_date'  => $data['from_date'],
            'to_date'    => $data['to_date'],
            'reason'     => $data['reason'],
            'status'     => 'Pending',
        ]);

        $leave->load(['student.schoolClass:id,name', 'student.section:id,name']);

        return response()->json(['success' => true, 'data' => $this->fmt($leave)], 201);
    }

    // ─── PUT /leaves/{leave} ───────────────────────────────────────────────────
    public function update(Request $request, StudentLeave $leave): JsonResponse
    {
        $user = $request->user();
        abort_if($user->role === 'parent', 403, 'Not allowed.');
        abort_if($leave->school_id !== $user->school_id, 403, 'Forbidden.');

        $data = $request->validate([
            'status'  => 'required|in:Approved,Rejected',
            'remarks' => 'nullable|string|max:500',
        ]);

        $leave->update([
            'status'      => $data['status'],
            'remarks'     => $data['remarks'] ?? null,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // Auto-mark attendance as 'Leave' for every day in the leave period
        $attendanceMarked = 0;
        $attendanceError  = null;

        if ($data['status'] === 'Approved') {
            try {
                $leave->refresh();   // ensure from_date/to_date are fresh Carbon objects
                $leave->load('student');
                $student = $leave->student;

                if ($student) {
                    $academicYear = AcademicYear::where('school_id', $leave->school_id)
                        ->where('is_current', true)
                        ->first();

                    if ($academicYear) {
                        $current = $leave->from_date->copy();

                        while ($current->lte($leave->to_date)) {
                            Attendance::updateOrCreate(
                                [
                                    'school_id'  => $leave->school_id,
                                    'student_id' => $student->id,
                                    'date'       => $current->toDateString(),
                                ],
                                [
                                    'class_id'         => $student->class_id,
                                    'section_id'       => $student->section_id,
                                    'academic_year_id' => $academicYear->id,
                                    'status'           => 'Leave',
                                    'note'             => "Approved {$leave->leave_type} leave",
                                    'marked_by'        => $user->id,
                                ]
                            );

                            $attendanceMarked++;
                            $current->addDay();
                        }
                    } else {
                        $attendanceError = 'No active academic year found.';
                        \Log::warning("LeaveController: no active academic year for school {$leave->school_id}");
                    }
                } else {
                    $attendanceError = 'Student not found.';
                    \Log::warning("LeaveController: student not found for leave {$leave->id}");
                }
            } catch (\Throwable $e) {
                $attendanceError = $e->getMessage();
                \Log::error("LeaveController attendance marking failed for leave {$leave->id}: " . $e->getMessage());
            }
        }

        $leave->load(['student.schoolClass:id,name', 'student.section:id,name', 'reviewedBy:id,name']);

        return response()->json([
            'success'           => true,
            'data'              => $this->fmt($leave),
            'attendance_marked' => $attendanceMarked,
            'attendance_error'  => $attendanceError,
        ]);
    }

    // ─── DELETE /leaves/{leave} ────────────────────────────────────────────────
    public function destroy(Request $request, StudentLeave $leave): JsonResponse
    {
        $user = $request->user();
        abort_if($leave->school_id !== $user->school_id, 403, 'Forbidden.');

        if ($user->role === 'parent') {
            abort_if($leave->applied_by !== $user->id, 403, 'Not your leave.');
            abort_if($leave->status !== 'Pending', 422, 'Only pending leaves can be cancelled.');
        }

        $leave->delete();

        return response()->json(['success' => true, 'message' => 'Leave cancelled.']);
    }
}
