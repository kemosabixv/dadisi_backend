<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivateMessage;
use App\Services\Contracts\MessageServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function __construct(private MessageServiceContract $messageService)
    {
    }
    /**
     * Get a pre-signed URL for uploading an encrypted message to R2.
     */
    public function getUploadUrl(): JsonResponse
    {
        try {
            $result = $this->messageService->getUploadUrl();
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to generate upload URL', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to generate upload URL'], 500);
        }
    }

    /**
     * Store message metadata after client has uploaded encrypted content to R2.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'recipient_id' => 'required|exists:users,id',
                'r2_object_key' => 'required|string',
                'encrypted_key_package' => 'required|string',
                'nonce' => 'required|string',
            ]);

            $message = $this->messageService->sendMessage($validated);

            return response()->json([
                'data' => $message,
                'message' => 'Message sent successfully.',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to store message', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to send message'], 500);
        }
    }

    /**
     * Get the user's conversations (grouped by conversation partner).
     */
    public function conversations(): JsonResponse
    {
        try {
            $conversations = $this->messageService->getConversations();
            return response()->json(['data' => $conversations]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve conversations', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve conversations'], 500);
        }
    }

    /**
     * Get messages in a conversation with a specific user.
     */
    public function show(int $partnerId): JsonResponse
    {
        try {
            $messages = $this->messageService->getConversation($partnerId);
            return response()->json($messages);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve messages', ['error' => $e->getMessage(), 'partner_id' => $partnerId]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve messages'], 500);
        }
    }

    /**
     * Get a pre-signed URL to download an encrypted message from R2.
     */
    public function getVaultUrl(PrivateMessage $message): JsonResponse
    {
        try {
            $result = $this->messageService->getVaultUrl($message->id);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to generate vault URL', ['error' => $e->getMessage(), 'message_id' => $message->id]);
            return response()->json(['success' => false, 'message' => 'Failed to generate download URL'], 500);
        }
    }
}
