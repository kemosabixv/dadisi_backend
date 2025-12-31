<?php

namespace App\Services;

use App\Services\Contracts\MessageServiceContract;
use App\Models\PrivateMessage;
use App\Models\User;
use App\Events\MessageSent;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Message Service
 *
 * Handles encrypted message operations including upload/download URLs and conversations.
 */
class MessageService implements MessageServiceContract
{
    /**
     * Get pre-signed upload URL for encrypted message
     */
    public function getUploadUrl(): array
    {
        try {
            $objectKey = 'messages/' . Auth::id() . '/' . Str::uuid() . '.enc';

            // Access the S3 client through Laravel's filesystem manager
            $s3Client = app('filesystem')->disk('r2')->getAdapter()->getClient();
            
            $cmd = $s3Client->getCommand('PutObject', [
                'Bucket' => config('filesystems.disks.r2.bucket'),
                'Key'    => $objectKey,
            ]);

            $request = $s3Client->createPresignedRequest($cmd, '+20 minutes');
            $url = (string)$request->getUri();

            return [
                'upload_url' => $url,
                'object_key' => $objectKey,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate upload URL', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Store message metadata after encrypted content upload
     */
    public function sendMessage(array $data): array
    {
        try {
            // Verify the recipient has a public key set up
            $recipient = User::find($data['recipient_id']);
            if (!$recipient->publicKey) {
                throw new \Exception('Recipient has not set up encrypted messaging.');
            }

            $message = PrivateMessage::create([
                'sender_id' => Auth::id(),
                'recipient_id' => $data['recipient_id'],
                'r2_object_key' => $data['r2_object_key'],
                'encrypted_key_package' => $data['encrypted_key_package'],
                'nonce' => $data['nonce'],
            ]);

            // Broadcast via WebSocket for real-time delivery
            broadcast(new MessageSent($message))->toOthers();

            return $message->load('sender:id,username,profile_picture_path')->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to store message', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get user's conversations (grouped by partner)
     */
    public function getConversations(): array
    {
        try {
            $userId = Auth::id();

            // Get all conversation partners
            $sentToUsers = PrivateMessage::where('sender_id', $userId)
                ->select('recipient_id as partner_id')
                ->distinct();

            $receivedFromUsers = PrivateMessage::where('recipient_id', $userId)
                ->select('sender_id as partner_id')
                ->distinct();

            $partnerIds = $sentToUsers->union($receivedFromUsers)->pluck('partner_id');

            $conversations = [];
            foreach ($partnerIds as $partnerId) {
                $latestMessage = PrivateMessage::where(function ($q) use ($userId, $partnerId) {
                    $q->where('sender_id', $userId)->where('recipient_id', $partnerId);
                })->orWhere(function ($q) use ($userId, $partnerId) {
                    $q->where('sender_id', $partnerId)->where('recipient_id', $userId);
                })->latest()->first();

                $unreadCount = PrivateMessage::where('sender_id', $partnerId)
                    ->where('recipient_id', $userId)
                    ->whereNull('read_at')
                    ->count();

                $partner = User::select('id', 'username', 'profile_picture_path')->find($partnerId);

                $conversations[] = [
                    'partner' => $partner,
                    'last_message_at' => $latestMessage?->created_at,
                    'unread_count' => $unreadCount,
                ];
            }

            // Sort by last message (newest first)
            usort($conversations, fn($a, $b) => $b['last_message_at'] <=> $a['last_message_at']);

            return $conversations;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve conversations', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get messages in a conversation with a specific user
     */
    public function getConversation(int $partnerId): LengthAwarePaginator
    {
        try {
            $userId = Auth::id();

            $messages = PrivateMessage::where(function ($q) use ($userId, $partnerId) {
                $q->where('sender_id', $userId)->where('recipient_id', $partnerId);
            })->orWhere(function ($q) use ($userId, $partnerId) {
                $q->where('sender_id', $partnerId)->where('recipient_id', $userId);
            })
            ->with('sender:id,username,profile_picture_path')
            ->orderBy('created_at', 'asc')
            ->paginate(50);

            return $messages;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve messages', ['error' => $e->getMessage(), 'partner_id' => $partnerId]);
            throw $e;
        }
    }

    /**
     * Get pre-signed download URL for encrypted message
     */
    public function getVaultUrl(int $messageId): array
    {
        try {
            $message = PrivateMessage::findOrFail($messageId);

            // Verify user is sender or recipient
            if ($message->sender_id !== Auth::id() && $message->recipient_id !== Auth::id()) {
                throw new \Exception('Unauthorized access to message');
            }

            // Mark as read if recipient
            if ($message->recipient_id === Auth::id()) {
                $message->markAsRead();
            }

            // Access the S3 client through Laravel's filesystem manager
            $s3Client = app('filesystem')->disk('r2')->getAdapter()->getClient();
            
            $cmd = $s3Client->getCommand('GetObject', [
                'Bucket' => config('filesystems.disks.r2.bucket'),
                'Key'    => $message->r2_object_key,
            ]);

            $request = $s3Client->createPresignedRequest($cmd, '+5 minutes');
            $url = (string)$request->getUri();

            return [
                'download_url' => $url,
                'encrypted_key_package' => $message->encrypted_key_package,
                'nonce' => $message->nonce,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate vault URL', ['error' => $e->getMessage(), 'message_id' => $messageId]);
            throw $e;
        }
    }
}
