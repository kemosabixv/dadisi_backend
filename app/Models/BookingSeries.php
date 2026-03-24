<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingSeries extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';

    const TYPE_SINGLE = 'single';
    const TYPE_RECURRING = 'recurring';
    const TYPE_FLEXIBLE = 'flexible';

    protected $fillable = [
        'user_id',
        'lab_space_id',
        'reference',
        'type',
        'total_hours',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'total_hours' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function labSpace(): BelongsTo
    {
        return $this->belongsTo(LabSpace::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(LabBooking::class, 'booking_series_id');
    }

    public function holds(): HasMany
    {
        return $this->hasMany(SlotHold::class, 'series_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(BookingAuditLog::class, 'series_id');
    }
}
