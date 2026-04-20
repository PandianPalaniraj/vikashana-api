<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\Student;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    private function fmtList(Exam $exam): array
    {
        return [
            'id'             => $exam->id,
            'name'           => $exam->name,
            'type'           => $exam->type,
            'status'         => $exam->status,
            'start_date'     => $exam->start_date?->toDateString() ?? '',
            'end_date'       => $exam->end_date?->toDateString() ?? '',
            'class'          => $exam->schoolClass ? ['id' => $exam->schoolClass->id, 'name' => $exam->schoolClass->name] : null,
            'academic_year'  => $exam->academicYear ? ['id' => $exam->academicYear->id, 'name' => $exam->academicYear->name] : null,
            'subjects_count' => $exam->subjects_count ?? 0,
        ];
    }

    private function fmtDetail(Exam $exam): array
    {
        $exam->loadMissing(['schoolClass:id,name', 'academicYear:id,name', 'subjects.subject:id,name']);
        return array_merge($this->fmtList($exam), [
            'subjects_count' => $exam->subjects->count(),
            'timetable'      => $exam->subjects->map(fn($es) => $this->fmtSubject($es))->values(),
        ]);
    }

    private function fmtSubject(ExamSubject $es): array
    {
        return [
            'id'               => $es->id,
            'subject_id'       => $es->subject_id,
            'subject'          => $es->subject?->name ?? '—',
            'date'             => $es->date?->toDateString() ?? '',
            'start_time'       => $es->start_time ? substr($es->start_time, 0, 5) : '',
            'duration_minutes' => $es->duration_minutes,
            'max_marks'        => (float) $es->max_marks,
            'pass_marks'       => (float) $es->pass_marks,
            'venue'            => $es->venue ?? '',
        ];
    }

    // GET /exams
    public function index(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $query = Exam::where('school_id', $schoolId)
            ->with(['schoolClass:id,name', 'academicYear:id,name'])
            ->withCount('subjects as subjects_count')
            ->orderByDesc('start_date');

        if ($request->filled('class_id'))         $query->where('class_id',         $request->class_id);
        if ($request->filled('academic_year_id')) $query->where('academic_year_id', $request->academic_year_id);
        if ($request->filled('status'))           $query->withStatus($request->status);

        $exams = $query->get()->map(fn($e) => $this->fmtList($e))->values();

        return response()->json(['success' => true, 'data' => $exams]);
    }

    // POST /exams
    public function store(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $request->validate([
            'name'             => 'required|string|max:200',
            'type'             => 'required|in:Unit Test,Mid Term,Final,Board,Internal,Other',
            'academic_year_id' => 'required|exists:academic_years,id',
            'class_id'         => 'required|exists:classes,id',
            'start_date'       => 'nullable|date',
            'end_date'         => 'nullable|date|after_or_equal:start_date',
        ]);

        // Status is auto-computed from dates in the Exam model accessor.
        $exam = Exam::create([
            'school_id'        => $schoolId,
            'academic_year_id' => $request->academic_year_id,
            'class_id'         => $request->class_id,
            'name'             => $request->name,
            'type'             => $request->type,
            'start_date'       => $request->start_date,
            'end_date'         => $request->end_date,
            'status'           => 'Upcoming',
        ]);

        $exam->load(['schoolClass:id,name', 'academicYear:id,name']);
        $exam->subjects_count = 0;

        return response()->json(['success' => true, 'message' => 'Exam created', 'data' => $this->fmtList($exam)], 201);
    }

    // GET /exams/{exam}
    public function show(Request $request, Exam $exam): JsonResponse
    {
        abort_if($exam->school_id !== $request->user()->school_id, 403);
        return response()->json(['success' => true, 'data' => $this->fmtDetail($exam)]);
    }

    // PUT /exams/{exam}
    public function update(Request $request, Exam $exam): JsonResponse
    {
        abort_if($exam->school_id !== $request->user()->school_id, 403);

        $request->validate([
            'name'       => 'sometimes|required|string|max:200',
            'type'       => 'sometimes|required|in:Unit Test,Mid Term,Final,Board,Internal,Other',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $data = [];
        if ($request->has('name'))       $data['name']       = $request->name;
        if ($request->has('type'))       $data['type']       = $request->type;
        if ($request->has('start_date')) $data['start_date'] = $request->start_date;
        if ($request->has('end_date'))   $data['end_date']   = $request->end_date;

        $wasCompleted = $exam->status === 'Completed';
        $exam->update($data);
        $newlyCompleted = !$wasCompleted && $exam->fresh()->status === 'Completed';

        // Push notification: when exam transitions into Completed (end_date passed)
        if ($newlyCompleted) {
            try {
                $exam->load('schoolClass');
                $classId = $exam->class_id;
                if ($classId) {
                    $parentUserIds = Student::where('school_id', $exam->school_id)
                        ->where('class_id', $classId)
                        ->with('parents')
                        ->get()
                        ->flatMap(fn($s) => $s->parents->whereNotNull('user_id')->pluck('user_id'))
                        ->unique()
                        ->toArray();

                    if (!empty($parentUserIds)) {
                        app(PushNotificationService::class)->sendToUsers($parentUserIds,
                            '📝 Exam Results Published',
                            "{$exam->name} results are now available",
                            ['screen' => 'Marks']
                        );
                    }
                }
            } catch (\Throwable $e) {
                // Non-fatal
            }
        }

        return response()->json(['success' => true, 'message' => 'Exam updated', 'data' => $this->fmtDetail($exam)]);
    }

    // DELETE /exams/{exam}
    public function destroy(Request $request, Exam $exam): JsonResponse
    {
        abort_if($exam->school_id !== $request->user()->school_id, 403);
        $exam->delete();
        return response()->json(['success' => true, 'message' => 'Exam deleted']);
    }

    // GET /exams/{exam}/timetable
    public function timetable(Request $request, Exam $exam): JsonResponse
    {
        abort_if($exam->school_id !== $request->user()->school_id, 403);
        $exam->load('subjects.subject:id,name');
        return response()->json([
            'success' => true,
            'data'    => $exam->subjects->map(fn($es) => $this->fmtSubject($es))->values(),
        ]);
    }

    // POST /exams/{exam}/timetable
    public function addTimetableSlot(Request $request, Exam $exam): JsonResponse
    {
        abort_if($exam->school_id !== $request->user()->school_id, 403);

        $request->validate([
            'subject_id'       => 'required|exists:subjects,id',
            'date'             => 'nullable|date',
            'start_time'       => 'nullable|string|max:10',
            'duration_minutes' => 'nullable|integer|min:1',
            'max_marks'        => 'nullable|numeric|min:0',
            'pass_marks'       => 'nullable|numeric|min:0',
            'venue'            => 'nullable|string|max:100',
        ]);

        if (ExamSubject::where('exam_id', $exam->id)->where('subject_id', $request->subject_id)->exists()) {
            return response()->json(['success' => false, 'message' => 'This subject is already in the exam schedule'], 422);
        }

        $es = ExamSubject::create([
            'exam_id'          => $exam->id,
            'subject_id'       => $request->subject_id,
            'date'             => $request->date,
            'start_time'       => $request->start_time,
            'duration_minutes' => $request->duration_minutes ?? 120,
            'max_marks'        => $request->input('max_marks', 100),
            'pass_marks'       => $request->input('pass_marks', 35),
            'venue'            => $request->venue,
        ]);

        $es->load('subject:id,name');

        return response()->json(['success' => true, 'message' => 'Subject added to exam', 'data' => $this->fmtSubject($es)], 201);
    }

    // PUT /exams/{exam}/timetable/{examSubject}
    public function updateTimetableSlot(Request $request, Exam $exam, ExamSubject $examSubject): JsonResponse
    {
        abort_if($exam->school_id !== $request->user()->school_id, 403);
        abort_if($examSubject->exam_id !== $exam->id, 403);

        $request->validate([
            'date'             => 'nullable|date',
            'start_time'       => 'nullable|string|max:10',
            'duration_minutes' => 'nullable|integer|min:1',
            'max_marks'        => 'nullable|numeric|min:0',
            'pass_marks'       => 'nullable|numeric|min:0',
            'venue'            => 'nullable|string|max:100',
        ]);

        $examSubject->update($request->only(['date', 'start_time', 'duration_minutes', 'max_marks', 'pass_marks', 'venue']));
        $examSubject->load('subject:id,name');

        return response()->json(['success' => true, 'message' => 'Slot updated', 'data' => $this->fmtSubject($examSubject)]);
    }

    // DELETE /exams/{exam}/timetable/{examSubject}
    public function deleteTimetableSlot(Request $request, Exam $exam, ExamSubject $examSubject): JsonResponse
    {
        abort_if($exam->school_id !== $request->user()->school_id, 403);
        abort_if($examSubject->exam_id !== $exam->id, 403);
        $examSubject->delete();
        return response()->json(['success' => true, 'message' => 'Subject removed from exam']);
    }

    // GET /exams/{exam}/marks
    public function getMarks(Request $request, Exam $exam): JsonResponse
    {
        abort_if($exam->school_id !== $request->user()->school_id, 403);

        $exam->load(['subjects.subject:id,name', 'subjects.marks']);

        $students = Student::where('school_id', $exam->school_id)
            ->where('class_id', $exam->class_id)
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name', 'admission_no']);

        // Build marks map: [student_id][exam_subject_id] => mark data
        $marksMap = [];
        foreach ($exam->subjects as $es) {
            foreach ($es->marks as $mark) {
                $marksMap[$mark->student_id][$es->id] = [
                    'marks_obtained' => $mark->marks_obtained !== null ? (float) $mark->marks_obtained : null,
                    'grade'          => $mark->grade,
                    'result'         => $mark->result,
                    'remarks'        => $mark->remarks,
                ];
            }
        }

        return response()->json([
            'success'  => true,
            'subjects' => $exam->subjects->map(fn($es) => $this->fmtSubject($es))->values(),
            'students' => $students->map(fn($s) => [
                'id'           => $s->id,
                'name'         => $s->name,
                'admission_no' => $s->admission_no,
                'marks'        => $marksMap[$s->id] ?? [],
            ])->values(),
        ]);
    }

    // POST /exams/{exam}/marks
    public function saveMarks(Request $request, Exam $exam): JsonResponse
    {
        abort_if($exam->school_id !== $request->user()->school_id, 403);

        $request->validate([
            'marks'                   => 'required|array',
            'marks.*.student_id'      => 'required|exists:students,id',
            'marks.*.exam_subject_id' => 'required|exists:exam_subjects,id',
            'marks.*.marks_obtained'  => 'nullable|numeric|min:0',
            'marks.*.grade'           => 'nullable|string|max:5',
            'marks.*.result'          => 'nullable|in:Pass,Fail,Absent,Withheld',
            'marks.*.remarks'         => 'nullable|string',
        ]);

        foreach ($request->marks as $m) {
            Mark::updateOrCreate(
                ['exam_subject_id' => $m['exam_subject_id'], 'student_id' => $m['student_id']],
                [
                    'exam_id'        => $exam->id,
                    'marks_obtained' => $m['marks_obtained'] ?? null,
                    'grade'          => $m['grade'] ?? null,
                    'result'         => $m['result'] ?? null,
                    'remarks'        => $m['remarks'] ?? null,
                    'entered_by'     => $request->user()->id,
                ]
            );
        }

        return response()->json(['success' => true, 'message' => 'Marks saved']);
    }

    // GET /exams/{exam}/report
    public function report(Request $request, Exam $exam): JsonResponse
    {
        abort_if($exam->school_id !== $request->user()->school_id, 403);

        $exam->load(['subjects.subject:id,name', 'subjects.marks']);

        $students = Student::where('school_id', $exam->school_id)
            ->where('class_id', $exam->class_id)
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name', 'admission_no']);

        $subjects  = $exam->subjects;
        $totalMax  = (float) $subjects->sum('max_marks');

        $results = $students->map(function ($s) use ($subjects, $totalMax) {
            $obtained     = 0.0;
            $subjectMarks = [];
            foreach ($subjects as $es) {
                $mark = $es->marks->firstWhere('student_id', $s->id);
                $m    = $mark?->marks_obtained !== null ? (float) $mark->marks_obtained : null;
                $subjectMarks[] = [
                    'exam_subject_id' => $es->id,
                    'subject'         => $es->subject?->name ?? '—',
                    'max_marks'       => (float) $es->max_marks,
                    'marks_obtained'  => $m,
                    'grade'           => $mark?->grade,
                    'result'          => $mark?->result,
                ];
                if ($m !== null) $obtained += $m;
            }
            $pct = $totalMax > 0 ? round(($obtained / $totalMax) * 100, 2) : null;
            return [
                'student_id'   => $s->id,
                'name'         => $s->name,
                'admission_no' => $s->admission_no,
                'total_marks'  => $obtained,
                'max_marks'    => $totalMax,
                'percentage'   => $pct,
                'grade'        => $this->calcGrade($pct),
                'subjects'     => $subjectMarks,
            ];
        })->values();

        // Sort by percentage desc and assign rank
        $sorted = $results->sortByDesc('percentage')->values();
        $sorted->transform(function ($r, $i) {
            $r['rank'] = $i + 1;
            return $r;
        });

        return response()->json([
            'success'   => true,
            'exam'      => ['name' => $exam->name, 'type' => $exam->type],
            'subjects'  => $subjects->map(fn($es) => [
                'id'        => $es->id,
                'name'      => $es->subject?->name ?? '—',
                'max_marks' => (float) $es->max_marks,
            ])->values(),
            'total_max' => $totalMax,
            'data'      => $sorted,
        ]);
    }

    private function calcGrade(?float $pct): string
    {
        if ($pct === null) return '—';
        if ($pct >= 90) return 'A+';
        if ($pct >= 80) return 'A';
        if ($pct >= 70) return 'B+';
        if ($pct >= 60) return 'B';
        if ($pct >= 50) return 'C';
        if ($pct >= 35) return 'D';
        return 'F';
    }
}
