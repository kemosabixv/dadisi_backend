<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function __construct(private ChatService $chatService)
    {
    }

    /**
     * List user's conversations.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $conversations = Conversation::forUser($user->id)
            ->with(['userOne:id,username,profile_picture_path', 'userTwo:id,username,profile_picture_path'])
            ->orderBy('last_message_at', 'desc')
            ->get()
            ->map(function ($conversation) use ($user) {
                $otherUser = $conversation->getOtherUser($user->id);
                return [
                    'id' => $conversation->id,
                    'partner' => [
                        'id' => $otherUser->id,
                        'username' => $otherUser->username,
                        'profile_picture_url' => $otherUser->profile_picture_url,
                    ],
                    'last_message_at' => $conversation->last_message_at,
                    'unread_count' => $conversation->messages()
                        ->where('sender_id', '!=', $user->id)
                        ->whereNull('read_at')
                        ->count(),
                ];
            });

        return response()->json([
            'data' => $conversations,
            'quota' => [
                'limit' => $this->chatService->getMonthlyLimit($user),
                'usage' => $this->chatService->getUsage($user),
            ]
        ]);
    }

    /**
     * Get or create a conversation with a specific user.
     */
    public function startConversation(User $user): JsonResponse
    {
        $currentUser = Auth::user();
        if ($currentUser->id === $user->id) {
            return response()->json(['message' => 'You cannot chat with yourself.'], 400);
        }

        $conversation = $this->chatService->getOrCreateConversation($currentUser->id, $user->id);

        return response()->json([
            'data' => [
                'id' => $conversation->id,
                'partner' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'profile_picture_url' => $user->profile_picture_url,
                ]
            ]
        ]);
    }

    /**
     * Get messages for a conversation.
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        if ($conversation->user_one_id !== $user->id && $conversation->user_two_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = $conversation->messages()->with('sender:id,username,profile_picture_path');

        if ($request->has('search')) {
            $query->where('content', 'LIKE', '%' . $request->search . '%');
        }

        $messages = $query->orderBy('created_at', 'asc')->paginate(50);

        // Mark as read
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json($messages);
    }

    /**
     * Send a message.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'content' => 'required_if:type,text|string|nullable',
            'type' => 'string|in:text,image,video,file',
            'r2_key' => 'required_unless:type,text|string|nullable',
            'file_name' => 'string|nullable',
            'file_size' => 'integer|nullable',
        ]);

        try {
            $message = $this->chatService->sendMessage($validated);

            return response()->json([
                'data' => $message->load('sender:id,username,profile_picture_path'),
                'usage' => $this->chatService->getUsage(Auth::user()),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Delete a single message.
     */
    public function deleteMessage(ChatMessage $message): JsonResponse
    {
        if ($message->sender_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message->delete(); // Soft delete as defined in model

        return response()->json(['message' => 'Message deleted successfully.']);
    }

    /**
     * Delete a conversation (for the current user only).
     */
    public function deleteConversation(Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        if ($conversation->user_one_id === $user->id) {
            $conversation->update(['user_one_deleted_at' => now()]);
        } elseif ($conversation->user_two_id === $user->id) {
            $conversation->update(['user_two_deleted_at' => now()]);
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['message' => 'Conversation deleted.']);
    }
}
