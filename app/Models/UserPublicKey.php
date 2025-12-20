<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPublicKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'public_key',
    ];

    /**
     * Get the user this public key belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
