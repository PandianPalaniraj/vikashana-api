<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Student;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * GET /api/v1/attendance
     * Query: class_id, section_id, date
     */
    public function index(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $query = Attendance::where('school_id', $schoolId);

        if ($request->class_id)   $query->where('class_id',   $request->class_id);
        if ($request->section_id) $query->where('section_id', $request->section_id);
        if ($request->date)       $query->whereDate('date',   $request->date);

        $records = $query->get(['student_id', 'status', 'date', 'note']);

        return response()->json([
            'success' => true,
            'data'    => $records->map(fn($r) => [
                'student_id' => $r->student_id,
                'status'     => $r->status,
                'date'       => $r->date->toDateString(),
                'note'       => $r->note,
            ]),
        ]);
    }

    /**
     * POST /api/v1/attendance  (bulk upsert)
     * Body: { class_id, section_id, date, records: [{student_id, status, note?}] }
     */
    public function markBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id'               => 'required|exists:classes,id',
            'section_id'             => 'required|exists:sections,id',
            'date'                   => 'required|date',
            'records'                => 'required|array|min:1',
            'records.*.student_id'   => 'required|exists:students,id',
            'records.*.status'       => 'required|in:Present,Absent,Late,Leave',
            'records.*.note'         => 'nullable|string|max:255',
        ]);

        $schoolId = $request->user()->school_id;
        $markedBy = $request->user()->id;

        $academicYear = AcademicYear::where('school_id', $schoolId)
            ->where('is_current', true)
            ->first();

        if (! $academicYear) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic year found. Please configure one in settings.',
            ], 422);
        }

        $saved = 0;
        DB::transaction(function () use ($validated, $schoolId, $markedBy, $academicYear, &$saved) {
            foreach ($validated['records'] as $rec) {
                Attendance::updateOrCreate(
                    ['student_id' => $rec['student_id'], 'date' => $validated['date']],
                    [
                        'school_id'        => $schoolId,
                        'class_id'         => $validated['class_id'],
                        'section_id'       => $validated['section_id'],
                        'academic_year_id' => $academicYear->id,
                        'status'           => $rec['status'],
                        'note'             => $rec['note'] ?? null,
                        'marked_by'        => $markedBy,
                    ]
                );
                $saved++;
            }
        });

        ActivityLog::log(
            $request->user()->id, $schoolId,
            'attendance', 'attendance',
            "Marked attendance for class_id {$validated['class_id']} on {$validated['date']} ({$saved} students)",
            '📅'
        );

        // Push notification: notify parents of absent students
        $absentIds = collect($validated['records'])
            ->where('status', 'Absent')
            ->pluck('student_id')
            ->toArray();

        if (!empty($absentIds)) {
            $push = app(PushNotificationService::class);
            $students = Student::whereIn('id', $absentIds)
                ->with('parents')
                ->get();

            foreach ($students as $student) {
                $parentUserIds = $student->parents
                    ->whereNotNull('user_id')
                    ->pluck('user_id')
                    ->toArray();

                if (!empty($parentUserIds)) {
                    $push->sendToUsers($parentUserIds,
                        '📅 Attendance Alert',
                        "{$student->name} was marked Absent today ({$validated['date']})",
                        ['screen' => 'Attendance']
                    );
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Attendance saved for {$saved} students",
            'saved'   => $saved,
        ]);
    }

    /**
     * GET /api/v1/attendance/summary?date=&class_id=&section_id=
     */
    public function summary(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $date     = $request->input('date', now()->toDateString());

        $query = Attendance::where('school_id', $schoolId)->whereDate('date', $date);

        if ($request->class_id)   $query->where('class_id',   $request->class_id);
        if ($request->section_id) $query->where('section_id', $request->section_id);

        $records = $query->get(['status']);
        $total   = $records->count();
        $present = $records->whereIn('status', ['Present', 'Late', 'Half Day'])->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'total'   => $total,
                'present' => $records->where('status', 'Present')->count(),
                'absent'  => $records->where('status', 'Absent')->count(),
                'late'    => $records->whereIn('status', ['Late', 'Half Day'])->count(),
                'leave'   => $records->where('status', 'Leave')->count(),
                'percent' => $total > 0 ? round($present / $total * 100, 1) : 0,
            ],
        ]);
    }

    /**
     * GET /api/v1/attendance/report?class_id=&section_id=&month=&year=
     */
    public function report(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $month    = (int) $request->input('month', now()->month);
        $year     = (int) $request->input('year',  now()->year);

        $query = Attendance::where('school_id', $schoolId)
            ->whereMonth('date', $month)
            ->whereYear('date',  $year)
            ->with('student:id,name,admission_no');

        if ($request->class_id)   $query->where('class_id',   $request->class_id);
        if ($request->section_id) $query->where('section_id', $request->section_id);

        $records = $query->get();
        $grouped = $records->groupBy('student_id');

        $data = $grouped->map(function ($rows) {
            $student = $rows->first()->student;
            // Leave days are excused — exclude from total so they don't penalise attendance %
            $leaveCount   = $rows->where('status', 'Leave')->count();
            $effectiveRows = $rows->whereNotIn('status', ['Leave']);
            $present      = $effectiveRows->whereIn('status', ['Present', 'Late', 'Half Day'])->count();
            $total        = $effectiveRows->count();   // excludes Leave

            return [
                'student_id'   => $rows->first()->student_id,
                'name'         => $student?->name         ?? 'Unknown',
                'admission_no' => $student?->admission_no ?? '',
                'present'      => $rows->where('status', 'Present')->count(),
                'absent'       => $rows->where('status', 'Absent')->count(),
                'late'         => $rows->whereIn('status', ['Late', 'Half Day'])->count(),
                'leave'        => $leaveCount,
                'total'        => $rows->count(),  // total raw days (including leave, for info)
                'percent'      => $total > 0 ? round($present / $total * 100, 1) : 0,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * PUT /api/v1/attendance/{id}
     */
    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        abort_if($request->user()->school_id !== $attendance->school_id, 403, 'Forbidden');

        $validated = $request->validate([
            'status' => 'required|in:Present,Absent,Late,Leave',
            'note'   => 'nullable|string|max:255',
        ]);

        $attendance->update($validated);

        return response()->json(['success' => true, 'message' => 'Attendance updated']);
    }
}
