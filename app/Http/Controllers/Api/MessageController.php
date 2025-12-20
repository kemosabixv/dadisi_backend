<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivateMessage;
use App\Models\User;
use App\Events\MessageSent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    /**
     * Get a pre-signed URL for uploading an encrypted message to R2.
     */
    public function getUploadUrl(): JsonResponse
    {
        $objectKey = 'messages/' . Auth::id() . '/' . Str::uuid() . '.enc';

        // Generate pre-signed PUT URL for R2 (60 minute expiry)
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('r2');
        $url = $disk->temporaryUrl(
            $objectKey,
            now()->addMinutes(60),
            ['method' => 'PUT']
        );

        return response()->json([
            'upload_url' => $url,
            'object_key' => $objectKey,
        ]);
    }

    /**
     * Store message metadata after client has uploaded encrypted content to R2.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'r2_object_key' => 'required|string',
            'encrypted_key_package' => 'required|string',
            'nonce' => 'required|string',
        ]);

        // Verify the recipient has a public key set up
        $recipient = User::find($validated['recipient_id']);
        if (!$recipient->publicKey) {
            return response()->json([
                'message' => 'Recipient has not set up encrypted messaging.',
            ], 422);
        }

        $message = PrivateMessage::create([
            'sender_id' => Auth::id(),
            'recipient_id' => $validated['recipient_id'],
            'r2_object_key' => $validated['r2_object_key'],
            'encrypted_key_package' => $validated['encrypted_key_package'],
            'nonce' => $validated['nonce'],
        ]);

        // Broadcast via WebSocket for real-time delivery
        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'data' => $message->load('sender:id,username,profile_picture_path'),
            'message' => 'Message sent successfully.',
        ], 201);
    }

    /**
     * Get the user's conversations (grouped by conversation partner).
     */
    public function conversations(): JsonResponse
    {
        $userId = Auth::id();

        // Get latest message with each conversation partner
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

        return response()->json([
            'data' => $conversations,
        ]);
    }

    /**
     * Get messages in a conversation with a specific user.
     */
    public function show(int $partnerId): JsonResponse
    {
        $userId = Auth::id();

        $messages = PrivateMessage::where(function ($q) use ($userId, $partnerId) {
            $q->where('sender_id', $userId)->where('recipient_id', $partnerId);
        })->orWhere(function ($q) use ($userId, $partnerId) {
            $q->where('sender_id', $partnerId)->where('recipient_id', $userId);
        })
        ->with('sender:id,username,profile_picture_path')
        ->orderBy('created_at', 'asc')
        ->paginate(50);

        return response()->json($messages);
    }

    /**
     * Get a pre-signed URL to download an encrypted message from R2.
     */
    public function getVaultUrl(PrivateMessage $message): JsonResponse
    {
        // Verify user is sender or recipient
        if ($message->sender_id !== Auth::id() && $message->recipient_id !== Auth::id()) {
            abort(403, 'You do not have access to this message.');
        }

        // Mark as read if recipient
        if ($message->recipient_id === Auth::id()) {
            $message->markAsRead();
        }

        // Generate pre-signed GET URL for R2 (5 minute expiry)
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('r2');
        $url = $disk->temporaryUrl(
            $message->r2_object_key,
            now()->addMinutes(5)
        );

        return response()->json([
            'download_url' => $url,
            'encrypted_key_package' => $message->encrypted_key_package,
            'nonce' => $message->nonce,
        ]);
    }
}
