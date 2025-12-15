<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReconciliationRun extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'reconciliation_runs';

    protected $fillable = [
        'run_id',
        'started_at',
        'completed_at',
        'status',
        'period_start',
        'period_end',
        'county',
        'total_matched',
        'total_unmatched_app',
        'total_unmatched_gateway',
        'total_amount_mismatch',
        'total_app_amount',
        'total_gateway_amount',
        'total_discrepancy',
        'notes',
        'error_message',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'period_start' => 'date',
        'period_end' => 'date',
        'total_app_amount' => 'decimal:2',
        'total_gateway_amount' => 'decimal:2',
        'total_discrepancy' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->run_id)) {
                $model->run_id = Str::uuid();
            }
            if (empty($model->started_at)) {
                $model->started_at = now();
            }
        });
    }

    /**
     * Get the reconciliation items for this run.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReconciliationItem::class, 'reconciliation_run_id');
    }

    /**
     * Get the user who initiated this run.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Mark the run as completed.
     */
    public function markCompleted(string $status = 'success', ?string $errorMessage = null): void
    {
        $this->update([
            'completed_at' => now(),
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Add a reconciliation item to this run.
     */
    public function addItem(array $data): ReconciliationItem
    {
        return $this->items()->create($data);
    }
}
