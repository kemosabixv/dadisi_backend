<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $table = 'attendance_logs';

    protected $fillable = [
        'booking_id',
        'lab_id',
        'user_id',
        'status',
        'check_in_time',
        'slot_start_time',
        'marked_by_id',
        'notes',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'slot_start_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ==================== Constants ====================

    public const STATUS_ATTENDED = 'attended';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_PENDING = 'pending';

    // ==================== Relationships ====================

    /**
     * Get the booking associated with this attendance record.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(LabBooking::class, 'booking_id');
    }

    /**
     * Get the lab where the booking occurred.
     */
    public function lab(): BelongsTo
    {
        return $this->belongsTo(LabSpace::class, 'lab_id');
    }

    /**
     * Get the user who made the booking (null for guests).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the staff member who marked this attendance.
     */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by_id');
    }

    // ==================== Query Scopes ====================

    /**
     * Filter attendance by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Filter attendance by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Filter attendance by lab.
     */
    public function scopeForLab($query, $labId)
    {
        return $query->where('lab_id', $labId);
    }

    /**
     * Filter attendance for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
