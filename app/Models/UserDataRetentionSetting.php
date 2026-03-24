<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDataRetentionSetting extends Model
{
    use HasFactory;
    protected $fillable = [
        'data_type',
        'retention_days',
        'retention_minutes',
        'auto_delete',
        'is_soft_delete',
        'description',
        'notes',
        'is_enabled',
        'updated_by',
    ];

    protected $casts = [
        'auto_delete' => 'boolean',
        'is_soft_delete' => 'boolean',
        'is_enabled' => 'boolean',
        'retention_days' => 'integer',
        'retention_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who last updated this setting
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get retention setting by data type (returns days)
     */
    public static function getRetentionDays(string $dataType): int
    {
        return static::where('data_type', $dataType)
            ->where('auto_delete', true)
            ->value('retention_days') ?? 90; // Default 90 days
    }

    /**
     * Get retention in minutes for data type.
     * If retention_minutes is set, use it; otherwise convert retention_days to minutes.
     */
    public static function getRetentionMinutes(string $dataType): int
    {
        $setting = static::where('data_type', $dataType)
            ->where('auto_delete', true)
            ->first();

        if (!$setting) {
            return 30; // Default 30 minutes for temporary data
        }

        // Prefer retention_minutes if set, otherwise convert days to minutes
        if ($setting->retention_minutes) {
            return $setting->retention_minutes;
        }

        return ($setting->retention_days ?? 1) * 24 * 60;
    }

    /**
     * Check if auto-delete is enabled for data type
     */
    public static function shouldAutoDelete(string $dataType): bool
    {
        return static::where('data_type', $dataType)
            ->where('auto_delete', true)
            ->exists();
    }

    /**
     * Get all active retention settings
     */
    public static function getActiveSettings(): array
    {
        return static::where('auto_delete', true)
            ->pluck('retention_days', 'data_type')
            ->toArray();
    }

    /**
     * Get the cutoff date for this data type
     */
    public function getCutoffDate(): \DateTime
    {
        return now()->subDays($this->retention_days);
    }

    /**
     * Scope: Get enabled retention settings
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope: Get by data type
     */
    public function scopeByDataType($query, string $dataType)
    {
        return $query->where('data_type', $dataType);
    }

    /**
     * Get the deletion strategy description
     */
    public function getDeletionStrategyAttribute(): string
    {
        return $this->is_soft_delete ? 'Soft Delete' : 'Hard Delete';
    }

    /**
     * Get human-readable retention period
     */
    public function getRetentionPeriodAttribute(): string
    {
        $days = $this->retention_days;
        
        if ($days >= 365) {
            return (int)($days / 365) . ' year(s)';
        } elseif ($days >= 30) {
            return round($days / 30, 1) . ' month(s)';
        } else {
            return $days . ' day(s)';
        }
    }
}
