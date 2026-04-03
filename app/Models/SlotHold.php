<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotHold extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'lab_space_id',
        'starts_at',
        'ends_at',
        'expires_at',
        'payment_intent_id',
        'renewal_count',
        'user_id',
        'guest_email',
        'series_id',
        'total_price',
        'paid_amount',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'expires_at' => 'datetime',
        'total_price' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function labSpace(): BelongsTo
    {
        return $this->belongsTo(LabSpace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(BookingSeries::class, 'series_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
