<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutoRenewalJob extends Model
{
    use SoftDeletes;

    protected $table = 'auto_renewal_jobs';

    protected $fillable = [
        'subscription_id',
        'user_id',
        'status',
        'attempt_type',
        'attempt_number',
        'max_attempts',
        'scheduled_at',
        'executed_at',
        'payment_method',
        'amount',
        'currency',
        'transaction_id',
        'payment_gateway_response',
        'error_message',
        'next_retry_at',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'executed_at' => 'datetime',
        'next_retry_at' => 'datetime',
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
