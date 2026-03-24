<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Notifications\ChatQuotaExceededNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatService
{
    /**
     * Get or create a conversation between two users.
     */
    public function getOrCreateConversation(int $userOneId, int $userTwoId): Conversation
    {
        // Ensure user_one_id < user_two_id for uniqueness
        $u1 = min($userOneId, $userTwoId);
        $u2 = max($userOneId, $userTwoId);

        $conversation = Conversation::firstOrCreate(
            ['user_one_id' => $u1, 'user_two_id' => $u2]
        );

        // Reset deleted_at if a new message is being initiated
        if ($conversation->user_one_id === Auth::id()) {
            $conversation->update(['user_one_deleted_at' => null]);
        } else {
            $conversation->update(['user_two_deleted_at' => null]);
        }

        return $conversation;
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(array $data): ChatMessage
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($data['conversation_id']);
        
        // Verify user is part of the conversation
        if ($conversation->user_one_id !== $user->id && $conversation->user_two_id !== $user->id) {
            throw new \Exception('Unauthorized: You are not part of this conversation.');
        }

        // Check Quota
        if (!$this->hasQuota($user)) {
            // Send notification about quota end
            $user->notify(new ChatQuotaExceededNotification());
            throw new \Exception('You have reached your monthly message limit. Please upgrade your plan to continue chatting.');
        }

        return DB::transaction(function () use ($user, $conversation, $data) {
            $message = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'content' => $data['content'] ?? null,
                'type' => $data['type'] ?? 'text',
                'r2_key' => $data['r2_key'] ?? null,
                'file_name' => $data['file_name'] ?? null,
                'file_size' => $data['file_size'] ?? null,
            ]);

            $conversation->update(['last_message_at' => now()]);

            // Clear deleted_at for both users since new activity occurred
            $conversation->update([
                'user_one_deleted_at' => null,
                'user_two_deleted_at' => null
            ]);

            // Trigger Realtime Broadcast via Supabase
            $this->broadcastMessage($message);

            // Send persistent notification to recipient
            $recipient = $conversation->getOtherUser($user->id);
            $recipient->notify(new NewMessageNotification($message));

            return $message;
        });
    }

    /**
     * Check if user has enough quota for the current month.
     */
    public function hasQuota(User $user): bool
    {
        $limit = $this->getMonthlyLimit($user);
        
        if ($limit === -1) {
            return true; // Unlimited
        }

        $count = ChatMessage::where('sender_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return $count < $limit;
    }

    /**
     * Get the monthly message limit for a user.
     */
    public function getMonthlyLimit(User $user): int
    {
        // Try to get from active subscription first
        $subscription = $user->activeSubscription()->first();
        if ($subscription) {
            $limit = $subscription->plan?->getFeatureValue('monthly_chat_message_limit');
            if ($limit !== null) return (int) $limit;
        }

        // Fallback to community plan defaults (seeding usually handles this)
        return 150; 
    }

    /**
     * Get current usage for the month.
     */
    public function getUsage(User $user): int
    {
        return ChatMessage::where('sender_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    /**
     * Broadcast message to Supabase Realtime.
     */
    protected function broadcastMessage(ChatMessage $message)
    {
        $recipientId = $message->conversation->getOtherUser($message->sender_id)->id;

        try {
            \App\Services\SupabaseRealtimeService::push(
                $recipientId,
                'user',
                [
                    'type' => 'chat_message',
                    'message_id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender->username,
                    'content_preview' => \Illuminate\Support\Str::limit($message->content, 50),
                    'created_at' => $message->created_at->toISOString(),
                ]
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Supabase broadcast exception', ['error' => $e->getMessage()]);
        }
    }
}
