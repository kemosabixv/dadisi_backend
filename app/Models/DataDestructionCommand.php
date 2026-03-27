<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataDestructionCommand extends Model
{
    protected $table = 'data_destruction_commands';

    protected $fillable = [
        'command_name',
        'job_class',
        'data_type',
        'description',
        'notes',
        'frequency',
        'is_enabled',
        'supports_dry_run',
        'supports_sync',
        'is_critical',
        'affected_records_count',
        'last_run_at',
    ];

    protected $casts = [
        'command_name' => 'string',
        'job_class' => 'string',
        'data_type' => 'string',
        'frequency' => 'string',
        'is_enabled' => 'boolean',
        'supports_dry_run' => 'boolean',
        'supports_sync' => 'boolean',
        'is_critical' => 'boolean',
        'affected_records_count' => 'integer',
        'last_run_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope: Get enabled commands
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope: Get by frequency
     */
    public function scopeByFrequency($query, string $frequency)
    {
        return $query->where('frequency', $frequency);
    }

    /**
     * Scope: Get critical commands
     */
    public function scopeCritical($query)
    {
        return $query->where('is_critical', true);
    }

    /**
     * Check if command has been run before
     */
    public function hasBeenRun(): bool
    {
        return $this->last_run_at !== null;
    }

    /**
     * Get time since last run
     */
    public function getTimeSinceLastRunAttribute(): ?string
    {
        return $this->last_run_at?->diffForHumans();
    }

    /**
     * Get all commands by data type
     */
    public static function byDataType(string $dataType)
    {
        return static::where('data_type', $dataType)->get();
    }

    /**
     * Get all critical commands that are enabled
     */
    public static function getCriticalEnabled()
    {
        return static::where('is_critical', true)
            ->where('is_enabled', true)
            ->get();
    }

    /**
     * Mark command as successfully run
     */
    public function markAsRun(int $affectedCount = 0): void
    {
        $this->update([
            'last_run_at' => now(),
            'affected_records_count' => $affectedCount,
        ]);
    }

    /**
     * Get related retention setting
     */
    public function getRetentionSetting()
    {
        return UserDataRetentionSetting::where('data_type', $this->data_type)->first();
    }

    /**
     * Get retention days for this command
     */
    public function getRetentionDays(): int
    {
        $setting = $this->getRetentionSetting();
        return $setting?->retention_days ?? 90;
    }

    /**
     * Disable this command (admin action)
     */
    public function disable(): void
    {
        $this->update(['is_enabled' => false]);
    }

    /**
     * Enable this command (admin action)
     */
    public function enable(): void
    {
        $this->update(['is_enabled' => true]);
    }

    /**
     * Get all destruction metrics
     */
    public static function getMetrics(): array
    {
        $all = static::all();
        
        return [
            'total_commands' => $all->count(),
            'enabled_commands' => $all->where('is_enabled', true)->count(),
            'critical_commands' => $all->where('is_critical', true)->count(),
            'total_affected' => $all->sum('affected_records_count'),
            'last_run' => $all->max('last_run_at'),
            'never_run' => $all->whereNull('last_run_at')->count(),
        ];
    }
}
