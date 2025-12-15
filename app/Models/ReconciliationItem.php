<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReconciliationItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'reconciliation_items';

    protected $fillable = [
        'reconciliation_run_id',
        'transaction_id',
        'reference',
        'source',
        'transaction_date',
        'amount',
        'payer_name',
        'payer_phone',
        'payer_email',
        'county',
        'app_status',
        'gateway_status',
        'reconciliation_status',
        'match_reference',
        'discrepancy_amount',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'amount' => 'decimal:2',
        'discrepancy_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Get the reconciliation run for this item.
     */
    public function run()
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }

    /**
     * Mark this item as matched.
     */
    public function markMatched(string $matchReference): void
    {
        $this->update([
            'reconciliation_status' => 'matched',
            'match_reference' => $matchReference,
        ]);
    }

    /**
     * Mark this item as unmatched (from app).
     */
    public function markUnmatchedApp(): void
    {
        $this->update(['reconciliation_status' => 'unmatched_app']);
    }

    /**
     * Mark this item as unmatched (from gateway).
     */
    public function markUnmatchedGateway(): void
    {
        $this->update(['reconciliation_status' => 'unmatched_gateway']);
    }

    /**
     * Mark this item as amount mismatch.
     */
    public function markAmountMismatch(float $discrepancy): void
    {
        $this->update([
            'reconciliation_status' => 'amount_mismatch',
            'discrepancy_amount' => $discrepancy,
        ]);
    }
}
