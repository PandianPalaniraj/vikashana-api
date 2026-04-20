<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Homework;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeworkController extends Controller
{
    private function fmt(Homework $hw): array
    {
        return [
            'id'          => $hw->id,
            'title'       => $hw->title,
            'description' => $hw->description ?? '',
            'due_date'    => $hw->due_date?->toDateString() ?? '',
            'status'      => $hw->status,
            'attachments' => $hw->attachments ?? [],
            'subject'     => $hw->subject     ? ['id' => $hw->subject->id,      'name' => $hw->subject->name]      : null,
            'class'       => $hw->schoolClass ? ['id' => $hw->schoolClass->id,  'name' => $hw->schoolClass->name]  : null,
            'section'     => $hw->section     ? ['id' => $hw->section->id,      'name' => $hw->section->name]      : null,
            'teacher'     => $hw->teacher     ? ['id' => $hw->teacher->id,      'name' => $hw->teacher->name]      : null,
            'created_at'  => $hw->created_at?->toDateString() ?? '',
        ];
    }

    // GET /homework
    public function index(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $today    = now()->toDateString();

        $query = Homework::where('school_id', $schoolId)
            ->with(['subject:id,name', 'schoolClass:id,name', 'section:id,name', 'teacher:id,name'])
            ->orderBy('due_date');

        if ($request->filled('class_id'))   $query->where('class_id',   $request->class_id);
        if ($request->filled('section_id')) $query->where(fn($q) => $q->where('section_id', $request->section_id)->orWhereNull('section_id'));
        if ($request->filled('teacher_id')) $query->where('teacher_id', $request->teacher_id);
        if ($request->filled('status'))     $query->where('status',     $request->status);

        // Summary counts — unfiltered except class/teacher (no status filter)
        $baseQ = Homework::where('school_id', $schoolId);
        if ($request->filled('class_id'))   $baseQ->where('class_id',   $request->class_id);
        if ($request->filled('teacher_id')) $baseQ->where('teacher_id', $request->teacher_id);

        $summary = [
            'total'     => (clone $baseQ)->count(),
            'overdue'   => (clone $baseQ)->where('status', 'Active')->where('due_date', '<', $today)->count(),
            'due_today' => (clone $baseQ)->where('due_date', $today)->count(),
            'completed' => (clone $baseQ)->where('status', 'Completed')->count(),
        ];

        $perPage  = min((int) $request->input('per_page', 20), 200);
        $page     = max(1, (int) $request->input('page', 1));
        $total    = $query->count();
        $items    = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'success' => true,
            'data'    => $items->map(fn($hw) => $this->fmt($hw))->values(),
            'meta'    => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'summary' => $summary,
        ]);
    }

    // POST /homework
    public function store(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date'    => 'required|date',
            'subject_id'  => 'nullable|exists:subjects,id',
            'class_id'    => 'required|exists:classes,id',
            'section_id'  => 'nullable|exists:sections,id',
            'teacher_id'  => 'nullable|exists:teachers,id',
            'status'      => 'nullable|in:Active,Completed,Cancelled',
        ]);

        // Auto-resolve teacher from logged-in user if not explicitly provided
        $teacherId = $request->teacher_id;
        if (! $teacherId) {
            $teacher   = Teacher::where('user_id', $request->user()->id)->where('school_id', $schoolId)->first();
            $teacherId = $teacher?->id;
        }

        // Auto-resolve current academic year (required, non-null column)
        $academicYear = AcademicYear::where('school_id', $schoolId)
            ->where('is_current', true)
            ->first()
            ?? AcademicYear::where('school_id', $schoolId)
                ->where('start_date', '<=', now())
                ->where('end_date',   '>=', now())
                ->first()
            ?? AcademicYear::where('school_id', $schoolId)
                ->orderByDesc('start_date')
                ->first();

        if (! $academicYear) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic year found. Please contact your admin to set up the current academic year.',
                'code'    => 'NO_ACADEMIC_YEAR',
            ], 422);
        }

        $hw = Homework::create([
            'school_id'        => $schoolId,
            'academic_year_id' => $academicYear->id,
            'subject_id'       => $request->subject_id,
            'class_id'         => $request->class_id,
            'section_id'       => $request->section_id,
            'teacher_id'       => $teacherId,
            'title'            => $request->title,
            'description'      => $request->description,
            'assigned_date'    => now(),
            'due_date'         => $request->due_date,
            'status'           => $request->input('status', 'Active'),
            'attachments'      => [],
        ]);

        $hw->load(['subject:id,name', 'schoolClass:id,name', 'section:id,name', 'teacher:id,name']);

        // Push notification: notify parents of students in the class
        try {
            $push = app(PushNotificationService::class);
            $studentQuery = Student::where('school_id', $schoolId)
                ->where('class_id', $request->class_id)
                ->with('parents');
            if ($request->section_id) {
                $studentQuery->where('section_id', $request->section_id);
            }
            $parentUserIds = $studentQuery->get()
                ->flatMap(fn($s) => $s->parents->whereNotNull('user_id')->pluck('user_id'))
                ->unique()
                ->toArray();

            if (!empty($parentUserIds)) {
                $subjectName = $hw->subject?->name ?? 'Homework';
                $push->sendToUsers($parentUserIds,
                    "📚 New {$subjectName} Homework",
                    "{$hw->title} — Due: {$hw->due_date->format('d M')}",
                    ['screen' => 'Homework']
                );
            }
        } catch (\Throwable $e) {
            // Non-fatal — homework was still created
        }

        return response()->json(['success' => true, 'message' => 'Homework assigned', 'data' => $this->fmt($hw)], 201);
    }

    // GET /homework/{homework}
    public function show(Request $request, Homework $homework): JsonResponse
    {
        abort_if($homework->school_id !== $request->user()->school_id, 403);
        $homework->load(['subject:id,name', 'schoolClass:id,name', 'section:id,name', 'teacher:id,name']);
        return response()->json(['success' => true, 'data' => $this->fmt($homework)]);
    }

    // PUT /homework/{homework}
    public function update(Request $request, Homework $homework): JsonResponse
    {
        abort_if($homework->school_id !== $request->user()->school_id, 403);

        $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'due_date'    => 'sometimes|required|date',
            'status'      => 'nullable|in:Active,Completed,Cancelled',
        ]);

        $data = [];
        if ($request->has('title'))       $data['title']       = $request->title;
        if ($request->has('description')) $data['description'] = $request->description;
        if ($request->has('due_date'))    $data['due_date']    = $request->due_date;
        if ($request->has('status'))      $data['status']      = $request->status;

        $homework->update($data);
        $homework->load(['subject:id,name', 'schoolClass:id,name', 'section:id,name', 'teacher:id,name']);

        return response()->json(['success' => true, 'message' => 'Homework updated', 'data' => $this->fmt($homework)]);
    }

    // DELETE /homework/{homework}
    public function destroy(Request $request, Homework $homework): JsonResponse
    {
        abort_if($homework->school_id !== $request->user()->school_id, 403);
        $homework->delete();
        return response()->json(['success' => true, 'message' => 'Homework deleted']);
    }
}
