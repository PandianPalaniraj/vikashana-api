<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\Timetable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimetableController extends Controller
{
    private function fmt(Timetable $t): array
    {
        return [
            'id'            => $t->id,
            'day'           => $t->day,
            'period'        => $t->period,
            'period_number' => is_numeric($t->period) ? (int) $t->period : null,
            'start_time'    => $t->start_time ?? '',
            'end_time'      => $t->end_time ?? '',
            'subject'       => $t->subject ? ['id' => $t->subject->id, 'name' => $t->subject->name] : null,
            'teacher'       => $t->teacher ? ['id' => $t->teacher->id, 'name' => $t->teacher->name] : null,
        ];
    }

    private function fmtFull(Timetable $t): array
    {
        return array_merge($this->fmt($t), [
            'class'   => $t->schoolClass ? ['id' => $t->schoolClass->id, 'name' => $t->schoolClass->name] : null,
            'section' => $t->section     ? ['id' => $t->section->id,     'name' => $t->section->name]     : null,
        ]);
    }

    // GET /timetable/mine  — returns the logged-in teacher's own periods
    public function mine(Request $request): JsonResponse
    {
        $user     = $request->user();
        $schoolId = $user->school_id;

        $teacher = Teacher::where('user_id', $user->id)
            ->where('school_id', $schoolId)->first();

        if (! $teacher) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $slots = Timetable::where('school_id', $schoolId)
            ->where('teacher_id', $teacher->id)
            ->with(['subject:id,name', 'teacher:id,name', 'schoolClass:id,name', 'section:id,name'])
            ->orderByRaw("FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')")
            ->orderBy('period')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $slots->map(fn($t) => $this->fmtFull($t))->values(),
        ]);
    }

    // GET /timetable?class_id=&section_id=&academic_year_id=
    public function index(Request $request): JsonResponse
    {
        $request->validate(['class_id' => 'required|integer']);

        $q = Timetable::where('school_id', $request->user()->school_id)
            ->where('class_id', $request->class_id)
            ->with(['subject:id,name', 'teacher:id,name']);

        if ($request->filled('section_id')) {
            $q->where('section_id', $request->section_id);
        }
        if ($request->filled('academic_year_id')) {
            $q->where('academic_year_id', $request->academic_year_id);
        }

        return response()->json([
            'success' => true,
            'data'    => $q->get()->map(fn($t) => $this->fmt($t))->values(),
        ]);
    }

    // POST /timetable/bulk  — upsert array of slots
    public function bulk(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $request->validate([
            'class_id'           => 'required|exists:classes,id',
            'section_id'         => 'required|exists:sections,id',
            'academic_year_id'   => 'nullable|exists:academic_years,id',
            'slots'              => 'required|array|min:1',
            'slots.*.day'        => 'required|string|max:20',
            'slots.*.period'     => 'required|string|max:10',
            'slots.*.subject_id' => 'nullable|exists:subjects,id',
            'slots.*.teacher_id' => 'nullable|exists:teachers,id',
        ]);

        $saved = [];

        DB::transaction(function () use ($request, $schoolId, &$saved) {
            foreach ($request->slots as $slot) {
                $entry = Timetable::updateOrCreate(
                    [
                        'school_id'        => $schoolId,
                        'class_id'         => $request->class_id,
                        'section_id'       => $request->section_id,
                        'academic_year_id' => $request->academic_year_id,
                        'day'              => $slot['day'],
                        'period'           => $slot['period'],
                    ],
                    [
                        'subject_id' => $slot['subject_id'] ?? null,
                        'teacher_id' => $slot['teacher_id'] ?? null,
                    ]
                );
                $saved[] = $entry->load(['subject:id,name', 'teacher:id,name']);
            }
        });

        return response()->json([
            'success' => true,
            'data'    => collect($saved)->map(fn($t) => $this->fmt($t))->values(),
        ]);
    }

    // DELETE /timetable/{id}
    public function destroy(Request $request, Timetable $timetable): JsonResponse
    {
        abort_if($timetable->school_id !== $request->user()->school_id, 403, 'Forbidden');
        $timetable->delete();
        return response()->json(['success' => true, 'message' => 'Slot cleared']);
    }
}
