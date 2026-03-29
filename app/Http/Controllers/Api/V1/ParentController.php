<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Homework;
use App\Models\StudentLeave;
use App\Models\StudentParent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParentController extends Controller
{
    /**
     * GET /api/v1/parents/my-children
     * Returns all students linked to this parent user.
     */
    public function myChildren(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->role !== 'parent', 403, 'Forbidden');

        $children = StudentParent::where('user_id', $user->id)
            ->with([
                'student.schoolClass:id,name',
                'student.section:id,name',
                'student.attendance' => fn($q) => $q->whereDate('date', today()),
                'student.feeInvoices' => fn($q) => $q->whereIn('status', ['Unpaid', 'Partial']),
            ])
            ->get()
            ->filter(fn($p) => $p->student !== null)
            ->map(fn($p) => [
                'student_id'       => $p->student->id,
                'name'             => $p->student->name,
                'admission_no'     => $p->student->admission_no,
                'photo'            => $p->student->photo
                    ? asset('storage/' . $p->student->photo)
                    : null,
                'class'            => $p->student->schoolClass->name ?? '—',
                'section'          => $p->student->section->name ?? '—',
                'class_id'         => $p->student->class_id,
                'section_id'       => $p->student->section_id,
                'dob'              => $p->student->dob?->toDateString(),
                'status'           => $p->student->status,
                'relation'         => $p->relation,
                'today_attendance' => $p->student->attendance->first()?->status ?? 'Not Marked',
                'pending_fees'     => round(
                    $p->student->feeInvoices->sum(fn($i) => $i->total - $i->paid),
                    2
                ),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $children,
            'count'   => $children->count(),
        ]);
    }

    /**
     * GET /api/v1/parents/student/{studentId}/dashboard
     * Returns dashboard data for a specific child of this parent.
     */
    public function studentDashboard(Request $request, int $studentId): JsonResponse
    {
        $user = $request->user();
        abort_if($user->role !== 'parent', 403, 'Forbidden');

        // Verify this parent has access to this student
        $parentRecord = StudentParent::where('user_id', $user->id)
            ->where('student_id', $studentId)
            ->firstOrFail();

        $student = $parentRecord->student()
            ->with(['schoolClass:id,name', 'section:id,name', 'academicYear:id,name'])
            ->firstOrFail();

        // Recent attendance (last 30 days)
        $attendance = $student->attendance()
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get(['date', 'status']);

        $presentCount  = $attendance->whereIn('status', ['Present', 'Late'])->count();
        $attendancePct = $attendance->count() > 0
            ? round(($presentCount / $attendance->count()) * 100, 1)
            : 0;

        // Pending fees
        $pendingFees = $student->feeInvoices()
            ->whereIn('status', ['Unpaid', 'Partial'])
            ->sum(DB::raw('total - paid'));

        // Upcoming homework
        $homework = Homework::where('class_id', $student->class_id)
            ->where('status', 'Active')
            ->where('due_date', '>=', today())
            ->orderBy('due_date')
            ->limit(5)
            ->with('subject:id,name')
            ->get(['id', 'title', 'due_date', 'subject_id']);

        // Upcoming exams
        $exams = Exam::where('class_id', $student->class_id)
            ->where('status', 'Upcoming')
            ->orderBy('start_date')
            ->limit(3)
            ->get(['id', 'name', 'type', 'start_date']);

        // Leaves
        $leavesQuery = StudentLeave::where('student_id', $studentId)
            ->where('school_id', $student->school_id);
        $pendingLeaves  = (clone $leavesQuery)->where('status', 'Pending')->count();
        $approvedLeaves = (clone $leavesQuery)->where('status', 'Approved')->count();

        // Recent marks
        $marks = $student->marks()
            ->with(['exam:id,name', 'examSubject.subject:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'student' => [
                    'id'           => $student->id,
                    'name'         => $student->name,
                    'admission_no' => $student->admission_no,
                    'class'        => $student->schoolClass->name ?? '—',
                    'section'      => $student->section->name ?? '—',
                    'photo'        => $student->photo
                        ? asset('storage/' . $student->photo)
                        : null,
                ],
                'attendance_pct'    => $attendancePct,
                'attendance_recent' => $attendance->map(fn($a) => [
                    'date'   => $a->date instanceof \Carbon\Carbon
                        ? $a->date->toDateString()
                        : $a->date,
                    'status' => $a->status,
                ]),
                'pending_fees'      => round((float) $pendingFees, 2),
                'homework'          => $homework->map(fn($hw) => [
                    'id'       => $hw->id,
                    'title'    => $hw->title,
                    'due_date' => $hw->due_date instanceof \Carbon\Carbon
                        ? $hw->due_date->toDateString()
                        : $hw->due_date,
                    'subject'  => $hw->subject
                        ? ['id' => $hw->subject->id, 'name' => $hw->subject->name]
                        : null,
                ]),
                'leaves_pending'    => $pendingLeaves,
                'leaves_approved'   => $approvedLeaves,
                'exams'             => $exams->map(fn($ex) => [
                    'id'         => $ex->id,
                    'name'       => $ex->name,
                    'type'       => $ex->type,
                    'start_date' => $ex->start_date instanceof \Carbon\Carbon
                        ? $ex->start_date->toDateString()
                        : $ex->start_date,
                ]),
                'marks'             => $marks->map(fn($m) => [
                    'marks_obtained' => $m->marks_obtained,
                    'grade'          => $m->grade,
                    'exam'           => $m->exam
                        ? ['id' => $m->exam->id, 'name' => $m->exam->name]
                        : null,
                    'subject'        => $m->examSubject?->subject
                        ? ['id' => $m->examSubject->subject->id, 'name' => $m->examSubject->subject->name]
                        : null,
                ]),
            ],
        ]);
    }
}
