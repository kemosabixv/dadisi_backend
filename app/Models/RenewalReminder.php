<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RenewalReminder extends Model
{
    use SoftDeletes;

    protected $table = 'renewal_reminders';

    protected $fillable = [
        'subscription_id',
        'user_id',
        'reminder_type',
        'days_before_expiry',
        'scheduled_at',
        'sent_at',
        'is_sent',
        'channel',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'is_sent' => 'boolean',
        'metadata' => 'array',
    ];

    public function subscription()
    {
        return $this->belongsTo(PlanSubscription::class, 'subscription_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
