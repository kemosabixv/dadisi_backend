<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use App\Services\SupabaseRealtimeService;

class SupabaseChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toSupabase')) {
            return;
        }

        $data = $notification->toSupabase($notifiable);
        
        if (empty($data)) {
            return;
        }

        // Determine target
        $recipientType = $data['recipient_type'] ?? 'user';
        
        // Always try to resolve a userId if targeting a specific user
        $userId = $data['userId'] ?? $data['user_id'] ?? null;
        if (!$userId && $recipientType === 'user' && isset($notifiable->id)) {
            $userId = $notifiable->id;
        }
        
        // Handle staff targeted by permission/role
        $role = $data['role'] ?? null;
        $permission = $data['permission'] ?? null;

        Log::info('SupabaseChannel sending notification', [
            'userId' => $userId,
            'recipientType' => $recipientType,
            'role' => $role,
            'permission' => $permission
        ]);

        SupabaseRealtimeService::push(
            $userId,
            $recipientType,
            $data,
            $role,
            $permission
        );
    }
}
