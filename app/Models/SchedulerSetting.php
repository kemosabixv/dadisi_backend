<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchedulerSetting extends Model
{
    protected $fillable = [
        'command_name',
        'run_time',
        'frequency',
        'enabled',
        'description',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'run_time' => 'string', // e.g., '03:00'
    ];

    /**
     * Get the user who last updated this setting
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get only enabled schedulers
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Get scheduler setting by command name
     */
    public static function getByCommandName(string $name): ?self
    {
        return static::where('command_name', $name)->first();
    }

    /**
     * Update scheduler time
     */
    public static function updateScheduleTime(string $commandName, string $runTime): bool
    {
        $setting = static::where('command_name', $commandName)->first();
        if ($setting) {
            $setting->update(['run_time' => $runTime, 'updated_by' => auth()->id()]);
            return true;
        }
        return false;
    }
}
