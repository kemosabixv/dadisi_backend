<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDataRetentionSetting extends Model
{
    protected $fillable = [
        'data_type',
        'retention_days',
        'retention_minutes',
        'auto_delete',
        'description',
        'updated_by',
    ];

    protected $casts = [
        'auto_delete' => 'boolean',
        'retention_days' => 'integer',
        'retention_minutes' => 'integer',
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
}
