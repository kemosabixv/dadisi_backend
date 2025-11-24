<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class EmailVerification extends Model
{
    protected $table = 'email_verification_codes';

    protected $fillable = [
        'user_id',
        'code',
        'expires_at',
        'consumed_at',
    ];

    protected $dates = [
        'expires_at',
        'consumed_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    /**
     * User relation
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if code is expired
     */
    public function isExpired(): bool
    {
        if (is_null($this->expires_at)) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if code has been consumed
     */
    public function isConsumed(): bool
    {
        return ! is_null($this->consumed_at);
    }
}
