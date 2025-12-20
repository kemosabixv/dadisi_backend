<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EscrowConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'organizer_trust_level',
        'min_ticket_price',
        'max_ticket_price',
        'hold_days_after_event',
        'release_percentage_immediate',
        'is_active',
    ];

    protected $casts = [
        'min_ticket_price' => 'decimal:2',
        'max_ticket_price' => 'decimal:2',
        'hold_days_after_event' => 'integer',
        'release_percentage_immediate' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
