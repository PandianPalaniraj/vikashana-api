<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Feedback::where('school_id', $request->user()->school_id)
            ->orderByDesc('created_at');

        if ($request->filled('status'))   $q->where('status', $request->status);
        if ($request->filled('category')) $q->where('category', $request->category);

        $items = $q->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $items->items(),
            'meta'    => [
                'total'     => $items->total(),
                'per_page'  => $items->perPage(),
                'page'      => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => 'required|in:query,bug,feature,complaint',
            'title'    => 'required|string|max:200',
            'body'     => 'nullable|string',
            'priority' => 'in:low,medium,high,critical',
        ]);

        $feedback = Feedback::create([
            'school_id' => $request->user()->school_id,
            'user_id'   => $request->user()->id,
            'category'  => $data['category'],
            'title'     => $data['title'],
            'body'      => $data['body'] ?? '',
            'priority'  => $data['priority'] ?? 'medium',
            'status'    => 'new',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Feedback submitted successfully',
            'data'    => $feedback,
        ], 201);
    }
}
