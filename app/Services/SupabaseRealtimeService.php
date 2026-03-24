<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseRealtimeService
{
    /**
     * Push a notification to Supabase Realtime table.
     *
     * @param int|null $userId Specific user ID or null for staff/broadcast
     * @param string $recipientType 'user' or 'staff'
     * @param array $payload Notification data
     * @param string|null $role Target staff role
     * @param string|null $permission Target staff permission
     * @return void
     */
    public static function push(
        ?int $userId,
        string $recipientType,
        array $payload,
        ?string $role = null,
        ?string $permission = null
    ): void {
        $table = config('services.supabase.table', 'realtime_notifications_dev');

        try {
            // Standard columns in the Supabase notifications table
            $standardColumns = [
                'user_id',
                'recipient_type',
                'role',
                'permission',
                'type',
                'title',
                'message',
                'data',
                'link',
                'created_at'
            ];

            // Build base payload with standard fields
            $body = [
                'user_id' => $userId,
                'recipient_type' => $recipientType,
                'role' => $role,
                'permission' => $permission,
                'type' => $payload['type'] ?? 'generic',
                'title' => $payload['title'] ?? 'New Notification',
                'message' => $payload['message'] ?? null,
                'link' => $payload['link'] ?? null,
                'created_at' => now()->toIso8601String(),
            ];

            // Automatically collect all "extra" keys from the payload into the 'data' column
            // This ensures domain-specific data (like refund_id) is NOT lost.
            $extraData = $payload['data'] ?? [];
            foreach ($payload as $key => $value) {
                if (!in_array($key, $standardColumns)) {
                    $extraData[$key] = $value;
                }
            }
            $body['data'] = $extraData;

            // Use Http facade directly — proven reliable and avoids
            // container binding issues with the saeedvir/supabase package
            $url = config('services.supabase.url');
            $key = config('services.supabase.service_key');

            if (!$url || !$key) {
                return;
            }

            $response = Http::timeout(15)->withHeaders([
                'apikey'        => $key,
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
                'Prefer'        => 'return=minimal',
            ])->post("{$url}/rest/v1/{$table}", $body);

            if ($response->failed()) {
                Log::error('Supabase Realtime Push Failed', [
                    'table'    => $table,
                    'status'   => $response->status(),
                    'response' => $response->body(),
                    'payload'  => $body,
                ]);
                return;
            }

            Log::info('Supabase Realtime Push Successful', [
                'user_id'        => $userId,
                'recipient_type' => $recipientType,
                'table'          => $table,
            ]);
        } catch (\Exception $e) {
            Log::error('Supabase Realtime Push Exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'payload' => $payload
            ]);
        }
    }
}
