<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    /**
     * Send a notification to a single user.
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = PushToken::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        $this->send($tokens, $title, $body, $data);
    }

    /**
     * Send a notification to multiple users.
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): void
    {
        $tokens = PushToken::whereIn('user_id', $userIds)
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        $this->send($tokens, $title, $body, $data);
    }

    /**
     * Send a notification to all active users in a school.
     */
    public function sendToSchool(int $schoolId, string $title, string $body, array $data = [], array $roles = []): void
    {
        $query = PushToken::where('school_id', $schoolId)->where('is_active', true);

        if (!empty($roles)) {
            $query->whereHas('user', fn($q) => $q->whereIn('role', $roles));
        }

        $tokens = $query->pluck('token')->toArray();

        $this->send($tokens, $title, $body, $data);
    }

    /**
     * Send notifications in chunks of 100 (Expo API limit).
     */
    public function send(array $tokens, string $title, string $body, array $data = []): void
    {
        if (empty($tokens)) return;

        $chunks = array_chunk($tokens, 100);

        foreach ($chunks as $chunk) {
            $messages = array_map(fn($token) => [
                'to'    => $token,
                'title' => $title,
                'body'  => $body,
                'data'  => $data,
                'sound' => 'default',
            ], $chunk);

            try {
                $response = Http::withHeaders([
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ])->post(self::EXPO_PUSH_URL, $messages);

                if ($response->successful()) {
                    $results = $response->json('data') ?? [];
                    $this->handleDeliveryResults($chunk, $results);
                } else {
                    Log::warning('[Push] Expo API error', ['status' => $response->status()]);
                }
            } catch (\Throwable $e) {
                Log::error('[Push] Send failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Deactivate tokens that Expo reports as invalid.
     */
    private function handleDeliveryResults(array $tokens, array $results): void
    {
        foreach ($results as $i => $result) {
            if (($result['status'] ?? '') === 'error'
                && ($result['details']['error'] ?? '') === 'DeviceNotRegistered') {
                $token = $tokens[$i] ?? null;
                if ($token) {
                    PushToken::where('token', $token)->update(['is_active' => false]);
                    Log::info('[Push] Deactivated invalid token', ['token' => substr($token, 0, 30) . '...']);
                }
            }
        }
    }
}
