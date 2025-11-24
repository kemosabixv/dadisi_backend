<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDataRetentionSetting extends Model
{
    protected $fillable = [
        'data_type',
        'retention_days',
        'auto_delete',
        'description',
        'updated_by',
    ];

    protected $casts = [
        'auto_delete' => 'boolean',
        'retention_days' => 'integer',
    ];

    /**
     * Get the user who last updated this setting
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get retention setting by data type
     */
    public static function getRetentionDays(string $dataType): int
    {
        return static::where('data_type', $dataType)
            ->where('auto_delete', true)
            ->value('retention_days') ?? 90; // Default 90 days
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
