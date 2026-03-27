<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceBlockRollover extends Model
{
    use HasFactory;

    protected $table = 'maintenance_block_rollovers';

    protected $fillable = [
        'maintenance_block_id',
        'series_id',
        'original_booking_id',
        'rolled_over_booking_id',
        'original_booking_data',
        'status',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'original_booking_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ==================== Constants ====================

    public const STATUS_INITIATED = 'initiated';
    public const STATUS_PENDING_USER = 'pending_user';
    public const STATUS_ESCALATED = 'escalated';
    public const STATUS_ROLLED_OVER = 'rolled_over';
    public const STATUS_CANCELLED = 'cancelled';

    // ==================== Relationships ====================

    /**
     * Get the maintenance block that triggered this rollover.
     */
    public function maintenanceBlock(): BelongsTo
    {
        return $this->belongsTo(LabMaintenanceBlock::class, 'maintenance_block_id');
    }

    /**
     * Get the original booking that was affected by the maintenance block.
     */
    public function originalBooking(): BelongsTo
    {
        return $this->belongsTo(LabBooking::class, 'original_booking_id');
    }

    /**
     * Get the new booking assigned during rollover (null if failed).
     */
    public function rolledOverBooking(): BelongsTo
    {
        return $this->belongsTo(LabBooking::class, 'rolled_over_booking_id');
    }
}
