<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSchoolActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Super admin has no school — always allow
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Check if user account is active
        if ($user->status !== 'active') {
            $user->tokens()->delete();
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.',
                'code'    => 'ACCOUNT_DEACTIVATED',
            ], 401);
        }

        // Check if school exists and is active
        if ($user->school_id) {
            $school = $user->school;

            if (!$school) {
                $user->tokens()->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'School not found.',
                    'code'    => 'SCHOOL_NOT_FOUND',
                ], 401);
            }

            if (!$school->is_active) {
                $user->tokens()->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Your school account has been deactivated. Please contact Vikashana support.',
                    'code'    => 'SCHOOL_DEACTIVATED',
                ], 401);
            }
        }

        return $next($request);
    }
}
