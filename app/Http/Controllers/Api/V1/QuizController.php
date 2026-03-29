<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\QuizQuestion;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    private function fmt(QuizQuestion $q, bool $withAnswer = false): array
    {
        $data = [
            'id'          => $q->id,
            'question'    => $q->question,
            'option_a'    => $q->option_a,
            'option_b'    => $q->option_b,
            'option_c'    => $q->option_c,
            'option_d'    => $q->option_d,
            'difficulty'  => $q->difficulty,
            'explanation' => $q->explanation,
        ];
        if ($withAnswer) {
            $data['correct_answer'] = $q->correct_answer;
        }
        return $data;
    }

    /**
     * GET /quiz/classes
     * Returns classes that have active quiz questions for this school.
     */
    public function classes(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $rows = QuizQuestion::where('school_id', $schoolId)
            ->where('status', 'Active')
            ->selectRaw('class_id, COUNT(*) as question_count')
            ->groupBy('class_id')
            ->with('schoolClass:id,name')
            ->get();

        $data = $rows->map(fn($r) => [
            'class_id'       => $r->class_id,
            'class_name'     => $r->schoolClass->name ?? '—',
            'question_count' => (int) $r->question_count,
        ])->sortBy('class_name')->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /quiz/subjects?class_id=X
     * Returns subjects that have active questions for the given class.
     */
    public function subjects(Request $request): JsonResponse
    {
        $request->validate(['class_id' => 'required|integer']);
        $schoolId = $request->user()->school_id;

        $rows = QuizQuestion::where('school_id', $schoolId)
            ->where('class_id', $request->class_id)
            ->where('status', 'Active')
            ->selectRaw('subject_id, COUNT(*) as question_count')
            ->groupBy('subject_id')
            ->with('subject:id,name')
            ->get();

        $data = $rows->map(fn($r) => [
            'subject_id'     => $r->subject_id,
            'subject_name'   => $r->subject->name ?? '—',
            'question_count' => (int) $r->question_count,
        ])->sortBy('subject_name')->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /quiz/questions?class_id=X&subject_id=Y&limit=20
     * Returns shuffled questions WITHOUT the correct answer.
     * Answer is revealed via POST /quiz/check.
     */
    public function questions(Request $request): JsonResponse
    {
        $request->validate([
            'class_id'   => 'required|integer',
            'subject_id' => 'required|integer',
        ]);
        $schoolId = $request->user()->school_id;
        $limit    = min((int) $request->input('limit', 20), 50);

        $questions = QuizQuestion::where('school_id', $schoolId)
            ->where('class_id',   $request->class_id)
            ->where('subject_id', $request->subject_id)
            ->where('status', 'Active')
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $questions->map(fn($q) => $this->fmt($q, false)),
            'total'   => $questions->count(),
        ]);
    }

    /**
     * POST /quiz/check
     * Body: { question_id, answer: 'A'|'B'|'C'|'D' }
     * Returns whether the answer is correct + the correct answer + explanation.
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'question_id' => 'required|integer',
            'answer'      => 'required|in:A,B,C,D',
        ]);

        $q = QuizQuestion::where('school_id', $request->user()->school_id)
            ->where('id', $request->question_id)
            ->where('status', 'Active')
            ->firstOrFail();

        $correct = strtoupper($request->answer) === $q->correct_answer;

        return response()->json([
            'success'        => true,
            'correct'        => $correct,
            'correct_answer' => $q->correct_answer,
            'explanation'    => $q->explanation,
        ]);
    }

    // ── Admin CRUD ────────────────────────────────────────────────────────────

    /**
     * GET /quiz/manage?class_id=X&subject_id=Y
     */
    public function index(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $query = QuizQuestion::where('school_id', $schoolId)
            ->with(['schoolClass:id,name', 'subject:id,name']);

        if ($request->filled('class_id'))   $query->where('class_id',   $request->class_id);
        if ($request->filled('subject_id')) $query->where('subject_id', $request->subject_id);
        if ($request->filled('status'))     $query->where('status',     $request->status);

        $items = $query->latest()->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => collect($items->items())->map(fn($q) => array_merge($this->fmt($q, true), [
                'status'       => $q->status,
                'class'        => $q->schoolClass?->name,
                'subject'      => $q->subject?->name,
                'class_id'     => $q->class_id,
                'subject_id'   => $q->subject_id,
                'created_at'   => $q->created_at->toDateString(),
            ])),
            'meta' => [
                'total'    => $items->total(),
                'per_page' => $items->perPage(),
                'page'     => $items->currentPage(),
            ],
        ]);
    }

    /**
     * POST /quiz/manage
     */
    public function store(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $data = $request->validate([
            'class_id'       => 'required|exists:classes,id',
            'subject_id'     => 'required|exists:subjects,id',
            'question'       => 'required|string|max:1000',
            'option_a'       => 'required|string|max:500',
            'option_b'       => 'required|string|max:500',
            'option_c'       => 'required|string|max:500',
            'option_d'       => 'required|string|max:500',
            'correct_answer' => 'required|in:A,B,C,D',
            'explanation'    => 'nullable|string|max:1000',
            'difficulty'     => 'nullable|in:Easy,Medium,Hard',
            'status'         => 'nullable|in:Active,Inactive',
        ]);

        $q = QuizQuestion::create(array_merge($data, [
            'school_id'  => $schoolId,
            'created_by' => $request->user()->id,
            'difficulty' => $data['difficulty'] ?? 'Medium',
            'status'     => $data['status']     ?? 'Active',
        ]));

        return response()->json(['success' => true, 'message' => 'Question added', 'data' => $this->fmt($q, true)], 201);
    }

    /**
     * PUT /quiz/manage/{question}
     */
    public function update(Request $request, QuizQuestion $question): JsonResponse
    {
        abort_if($question->school_id !== $request->user()->school_id, 403);

        $data = $request->validate([
            'class_id'       => 'sometimes|exists:classes,id',
            'subject_id'     => 'sometimes|exists:subjects,id',
            'question'       => 'sometimes|string|max:1000',
            'option_a'       => 'sometimes|string|max:500',
            'option_b'       => 'sometimes|string|max:500',
            'option_c'       => 'sometimes|string|max:500',
            'option_d'       => 'sometimes|string|max:500',
            'correct_answer' => 'sometimes|in:A,B,C,D',
            'explanation'    => 'nullable|string|max:1000',
            'difficulty'     => 'nullable|in:Easy,Medium,Hard',
            'status'         => 'nullable|in:Active,Inactive',
        ]);

        $question->update($data);

        return response()->json(['success' => true, 'message' => 'Question updated', 'data' => $this->fmt($question->fresh(), true)]);
    }

    /**
     * DELETE /quiz/manage/{question}
     */
    public function destroy(Request $request, QuizQuestion $question): JsonResponse
    {
        abort_if($question->school_id !== $request->user()->school_id, 403);
        $question->delete();
        return response()->json(['success' => true, 'message' => 'Question deleted']);
    }
}
