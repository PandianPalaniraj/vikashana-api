<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SystemAnnouncement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemAnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $plan     = Subscription::where('school_id', $schoolId)->value('plan') ?? 'free';

        $announcements = SystemAnnouncement::where(function ($q) use ($schoolId, $plan) {
                $q->where('target', 'all')
                  ->orWhere(function ($q2) use ($plan) {
                      $q2->where('target', 'plan')
                         ->where('target_value', $plan);
                  })
                  ->orWhere(function ($q2) use ($schoolId) {
                      $q2->where('target', 'school')
                         ->where('target_value', (string) $schoolId);
                  });
            })
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                  ->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id', 'title', 'body', 'target', 'target_value', 'created_at']);

        return response()->json([
            'success' => true,
            'data'    => $announcements,
        ]);
    }
}
