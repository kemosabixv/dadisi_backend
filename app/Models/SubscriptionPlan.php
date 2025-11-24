<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'price',
        'currency',
        'is_recurring',
        'interval',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get subscriptions for this plan
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * Get member profiles with this membership type
     */
    public function memberProfiles(): HasMany
    {
        return $this->hasMany(MemberProfile::class, 'membership_type');
    }

    /**
     * Scope for active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for recurring plans
     */
    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }
}
