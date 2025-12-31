<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventRegistration extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'event_registrations';

    protected $fillable = [
        'event_id',
        'user_id',
        'ticket_id',
        'order_id',
        'confirmation_code',
        'status',
        'check_in_at',
        'waitlist_position',
        'qr_code_token',
        'qr_code_path',
        'reminded_24h_at',
        'reminded_1h_at',
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'waitlist_position' => 'integer',
        'reminded_24h_at' => 'datetime',
        'reminded_1h_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EventOrder::class, 'order_id');
    }
}
