<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'user_one_deleted_at',
        'user_two_deleted_at',
        'last_message_at',
    ];

    protected $casts = [
        'user_one_deleted_at' => 'datetime',
        'user_two_deleted_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];

    /**
     * Get user one.
     */
    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    /**
     * Get user two.
     */
    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /**
     * Get messages for this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * Scope: Conversations for a specific user that are not deleted by them.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_one_id', $userId)
              ->whereNull('user_one_deleted_at');
        })->orWhere(function ($q) use ($userId) {
            $q->where('user_two_id', $userId)
              ->whereNull('user_two_deleted_at');
        });
    }

    /**
     * Get the "other" user in the conversation.
     */
    public function getOtherUser(int $currentUserId): ?User
    {
        return $this->user_one_id === $currentUserId ? $this->userTwo : $this->userOne;
    }
}
