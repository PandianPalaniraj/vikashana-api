<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassController extends Controller
{
    // ── Classes ────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $classes = SchoolClass::where('school_id', $request->user()->school_id)
            ->with(['sections' => fn($q) => $q->orderBy('name')->select('id','class_id','name','capacity')])
            ->withCount([
                'sections as sections_count',
                'subjects as subjects_count',
                'students as students_count' => fn($q) => $q->where('status', 'Active'),
            ])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name', 'display_order'])
            ->map(fn($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'display_order'  => $c->display_order,
                'sections_count' => $c->sections_count,
                'subjects_count' => $c->subjects_count,
                'students_count' => $c->students_count,
                'sections'       => $c->sections->map(fn($s) => [
                    'id'       => $s->id,
                    'name'     => $s->name,
                    'capacity' => $s->capacity,
                ])->values(),
            ]);

        return response()->json(['success' => true, 'data' => $classes]);
    }

    public function store(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $request->validate([
            'name'          => ['required', 'string', 'max:50',
                                Rule::unique('classes')->where('school_id', $schoolId)],
            'display_order' => 'nullable|integer|min:0',
        ]);

        $class = SchoolClass::create([
            'school_id'     => $schoolId,
            'name'          => $request->name,
            'display_order' => $request->input('display_order', 0),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Class {$class->name} created",
            'data'    => [
                'id'            => $class->id,
                'name'          => $class->name,
                'display_order' => $class->display_order,
                'sections'      => [],
                'sections_count'=> 0,
                'subjects_count'=> 0,
                'students_count'=> 0,
            ],
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $class = SchoolClass::where('school_id', $request->user()->school_id)
            ->with([
                'sections' => fn($q) => $q->orderBy('name')->select('id','class_id','name','capacity'),
                'subjects' => fn($q) => $q->orderBy('name')->select('id','class_id','name','code','is_optional'),
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'       => $class->id,
                'name'     => $class->name,
                'sections' => $class->sections->map(fn($s) => [
                    'id'       => $s->id,
                    'name'     => $s->name,
                    'capacity' => $s->capacity,
                ])->values(),
                'subjects' => $class->subjects->map(fn($s) => [
                    'id'         => $s->id,
                    'name'       => $s->name,
                    'code'       => $s->code,
                    'is_elective'=> (bool) $s->is_optional,
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $class    = SchoolClass::where('school_id', $schoolId)->findOrFail($id);

        $request->validate([
            'name'          => ['sometimes','required','string','max:50',
                                Rule::unique('classes')->where('school_id', $schoolId)->ignore($class->id)],
            'display_order' => 'nullable|integer|min:0',
        ]);

        $class->update(array_filter([
            'name'          => $request->name,
            'display_order' => $request->display_order,
        ], fn($v) => $v !== null));

        return response()->json(['success' => true, 'message' => 'Class updated', 'data' => [
            'id'            => $class->id,
            'name'          => $class->name,
            'display_order' => $class->display_order,
        ]]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $class = SchoolClass::where('school_id', $request->user()->school_id)->findOrFail($id);
        $class->delete();
        return response()->json(['success' => true, 'message' => 'Class deleted']);
    }

    // ── Sections ───────────────────────────────────────────────

    public function sections(Request $request): JsonResponse
    {
        $query = Section::where('school_id', $request->user()->school_id);

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        $sections = $query->orderBy('name')->get(['id', 'name', 'class_id', 'capacity']);

        return response()->json(['success' => true, 'data' => $sections]);
    }

    public function storeSection(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'name'     => ['required','string','max:20',
                           Rule::unique('sections')->where(fn($q) => $q->where('class_id', $request->class_id))],
            'capacity' => 'nullable|integer|min:1|max:200',
        ]);

        $section = Section::create([
            'school_id' => $schoolId,
            'class_id'  => $request->class_id,
            'name'      => $request->name,
            'capacity'  => $request->input('capacity', 40),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Section {$section->name} created",
            'data'    => ['id' => $section->id, 'name' => $section->name, 'capacity' => $section->capacity, 'class_id' => $section->class_id],
        ], 201);
    }

    public function updateSection(Request $request, $id): JsonResponse
    {
        $section = Section::where('school_id', $request->user()->school_id)->findOrFail($id);

        $request->validate([
            'name'     => ['sometimes','required','string','max:20',
                           Rule::unique('sections')->where(fn($q) => $q->where('class_id', $section->class_id))->ignore($section->id)],
            'capacity' => 'nullable|integer|min:1|max:200',
        ]);

        $section->update(array_filter([
            'name'     => $request->name,
            'capacity' => $request->capacity,
        ], fn($v) => $v !== null));

        return response()->json(['success' => true, 'message' => 'Section updated', 'data' => [
            'id' => $section->id, 'name' => $section->name, 'capacity' => $section->capacity, 'class_id' => $section->class_id,
        ]]);
    }

    public function destroySection(Request $request, $id): JsonResponse
    {
        $section = Section::where('school_id', $request->user()->school_id)->findOrFail($id);
        $section->delete();
        return response()->json(['success' => true, 'message' => 'Section deleted']);
    }

    // ── Subjects ───────────────────────────────────────────────

    public function subjects(Request $request): JsonResponse
    {
        $subjects = Subject::where('school_id', $request->user()->school_id)
            ->when($request->filled('class_id'), fn($q) => $q->where('class_id', $request->class_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'class_id', 'is_optional']);

        return response()->json(['success' => true, 'data' => $subjects->map(fn($s) => [
            'id'          => $s->id,
            'name'        => $s->name,
            'code'        => $s->code,
            'class_id'    => $s->class_id,
            'is_elective' => (bool) $s->is_optional,
        ])]);
    }

    public function storeSubject(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $request->validate([
            'class_id'    => 'required|exists:classes,id',
            'name'        => ['required','string','max:100',
                              Rule::unique('subjects')->where(fn($q) => $q->where('class_id', $request->class_id)->where('school_id', $schoolId))],
            'code'        => 'nullable|string|max:20',
            'is_elective' => 'nullable|boolean',
        ]);

        $subject = Subject::create([
            'school_id'   => $schoolId,
            'class_id'    => $request->class_id,
            'name'        => $request->name,
            'code'        => $request->code,
            'is_optional' => $request->input('is_elective', false),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Subject {$subject->name} created",
            'data'    => ['id' => $subject->id, 'name' => $subject->name, 'code' => $subject->code, 'is_elective' => (bool) $subject->is_optional],
        ], 201);
    }

    public function updateSubject(Request $request, $id): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $subject  = Subject::where('school_id', $schoolId)->findOrFail($id);

        $request->validate([
            'name'        => ['sometimes','required','string','max:100',
                              Rule::unique('subjects')->where(fn($q) => $q->where('class_id', $subject->class_id)->where('school_id', $schoolId))->ignore($subject->id)],
            'code'        => 'nullable|string|max:20',
            'is_elective' => 'nullable|boolean',
        ]);

        $subject->update(array_filter([
            'name'        => $request->name,
            'code'        => $request->code,
            'is_optional' => $request->has('is_elective') ? (bool) $request->is_elective : null,
        ], fn($v) => $v !== null));

        return response()->json(['success' => true, 'message' => 'Subject updated', 'data' => [
            'id' => $subject->id, 'name' => $subject->name, 'code' => $subject->code, 'is_elective' => (bool) $subject->is_optional,
        ]]);
    }

    public function destroySubject(Request $request, $id): JsonResponse
    {
        $subject = Subject::where('school_id', $request->user()->school_id)->findOrFail($id);
        $subject->delete();
        return response()->json(['success' => true, 'message' => 'Subject deleted']);
    }

    // ── Students in class ──────────────────────────────────────

    public function students(Request $request, $id): JsonResponse
    {
        $query = \App\Models\Student::where('school_id', $request->user()->school_id)
            ->where('class_id', $id)
            ->where('status', 'Active')
            ->with(['section' => fn($q) => $q->select('id','name')])
            ->orderBy('name');

        if ($request->filled('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        $students = $query->get(['id','name','admission_no','gender','section_id']);

        return response()->json([
            'success' => true,
            'data'    => $students->map(fn($s) => [
                'id'           => $s->id,
                'name'         => $s->name,
                'admission_no' => $s->admission_no,
                'gender'       => $s->gender,
                'section'      => $s->section ? ['id' => $s->section->id, 'name' => $s->section->name] : null,
            ])->values(),
        ]);
    }

    // ── Academic Years ─────────────────────────────────────────

    public function academicYears(Request $request): JsonResponse
    {
        $years = AcademicYear::where('school_id', $request->user()->school_id)
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'start_date', 'end_date', 'is_current'])
            ->map(fn($y) => [
                'id'         => $y->id,
                'name'       => $y->name,
                'start_date' => $y->start_date?->toDateString() ?? '',
                'end_date'   => $y->end_date?->toDateString()   ?? '',
                'is_current' => (bool) $y->is_current,
            ]);

        return response()->json(['success' => true, 'data' => $years]);
    }

    public function storeAcademicYear(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $data = $request->validate([
            'name'       => 'required|string|max:20',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'is_current' => 'boolean',
        ]);

        if (!empty($data['is_current'])) {
            AcademicYear::where('school_id', $schoolId)->update(['is_current' => false]);
        }

        $year = AcademicYear::create([...$data, 'school_id' => $schoolId]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $year->id,
                'name'       => $year->name,
                'start_date' => $year->start_date?->toDateString() ?? '',
                'end_date'   => $year->end_date?->toDateString()   ?? '',
                'is_current' => (bool) $year->is_current,
            ],
        ], 201);
    }

    public function updateAcademicYear(Request $request, $id): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $year     = AcademicYear::where('school_id', $schoolId)->findOrFail($id);

        $data = $request->validate([
            'name'       => 'sometimes|string|max:20',
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date',
            'is_current' => 'boolean',
        ]);

        if (!empty($data['is_current'])) {
            AcademicYear::where('school_id', $schoolId)
                ->where('id', '!=', $id)
                ->update(['is_current' => false]);
        }

        $year->update($data);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $year->id,
                'name'       => $year->name,
                'start_date' => $year->start_date?->toDateString() ?? '',
                'end_date'   => $year->end_date?->toDateString()   ?? '',
                'is_current' => (bool) $year->is_current,
            ],
        ]);
    }

    public function deleteAcademicYear(Request $request, $id): JsonResponse
    {
        $year = AcademicYear::where('school_id', $request->user()->school_id)->findOrFail($id);
        $year->delete();
        return response()->json(['success' => true, 'message' => 'Academic year deleted']);
    }
}
