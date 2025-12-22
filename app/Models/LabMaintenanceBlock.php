<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class LabMaintenanceBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_space_id',
        'title',
        'reason',
        'starts_at',
        'ends_at',
        'recurring',
        'recurrence_rule',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'recurring' => 'boolean',
    ];

    // ==================== Relationships ====================

    /**
     * Get the lab space for this maintenance block.
     */
    public function labSpace(): BelongsTo
    {
        return $this->belongsTo(LabSpace::class);
    }

    /**
     * Get the user who created this maintenance block.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ==================== Scopes ====================

    /**
     * Scope to maintenance blocks that overlap with a given time range.
     */
    public function scopeOverlapping($query, Carbon $start, Carbon $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->where('starts_at', '<', $end)
              ->where('ends_at', '>', $start);
        });
    }

    /**
     * Scope to upcoming maintenance blocks.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>', now());
    }

    /**
     * Scope to active maintenance blocks (currently in progress).
     */
    public function scopeActive($query)
    {
        return $query->where('starts_at', '<=', now())
                     ->where('ends_at', '>=', now());
    }

    // ==================== Accessors ====================

    /**
     * Get the duration in hours.
     */
    public function getDurationHoursAttribute(): float
    {
        if (!$this->starts_at || !$this->ends_at) {
            return 0;
        }

        return round($this->starts_at->diffInMinutes($this->ends_at) / 60, 2);
    }
}
