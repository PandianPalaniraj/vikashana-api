<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMobileAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->school_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $subscription = Subscription::where('school_id', $user->school_id)->first();

        if (!$subscription || !$subscription->isMobileAllowed()) {
            return response()->json([
                'success'        => false,
                'message'        => 'Mobile app requires Pro or Premium plan. Contact your school admin to upgrade from Starter.',
                'mobile_blocked' => true,
            ], 403);
        }

        return $next($request);
    }
}
