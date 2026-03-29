<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    /**
     * Register or update a push token for the authenticated user.
     */
    public function store(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'platform' => 'required|in:ios,android',
        ]);

        $user = $request->user();

        PushToken::updateOrCreate(
            ['user_id' => $user->id, 'token' => $request->token],
            [
                'school_id' => $user->school_id,
                'platform'  => $request->platform,
                'is_active' => true,
            ]
        );

        return response()->json(['message' => 'Push token saved']);
    }

    /**
     * Deactivate/remove a push token (called on logout).
     */
    public function destroy(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        PushToken::where('user_id', $request->user()->id)
            ->where('token', $request->token)
            ->delete();

        return response()->json(['message' => 'Push token removed']);
    }
}
