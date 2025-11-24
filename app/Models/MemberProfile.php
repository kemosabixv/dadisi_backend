<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberProfile extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone_number',
        'date_of_birth',
        'gender',
        'county_id',
        'sub_county',
        'ward',
        'interests',
        'bio',
        'is_staff',
        'membership_type',
        'occupation',
        'emergency_contact_name',
        'emergency_contact_phone',
        'terms_accepted',
        'marketing_consent',
    ];

    protected $casts = [
        'interests' => 'json',
        'terms_accepted' => 'boolean',
        'marketing_consent' => 'boolean',
        'is_staff' => 'boolean',
        'date_of_birth' => 'date',
    ];

    /**
     * Parse first name and last name from user username (for backward compatibility)
     * Note: This method is deprecated as names are now handled in profiles directly
     */
    public function parseNameFromUser(): void
    {
        // Since user.username is now used for login, we don't parse names from it
        // Names are handled directly in the profile first_name/last_name fields
        return;
    }

    /**
     * Get the user that owns this profile
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the county for this profile
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /**
     * Get the user's active subscription
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'user_id', 'user_id');
    }

    /**
     * Get the subscription plan for this membership type
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'membership_type');
    }

    /**
     * Get full name from profile first_name and last_name
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return trim($this->first_name . ' ' . $this->last_name);
        }

        // Fallback to just first name if available
        return $this->first_name ?: '';
    }

    /**
     * Scope for filtering by county
     */
    public function scopeByCounty($query, $countyId)
    {
        return $query->where('county_id', $countyId);
    }

    /**
     * Scope for members who have accepted terms
     */
    public function scopeTermsAccepted($query)
    {
        return $query->where('terms_accepted', true);
    }

    /**
     * Scope for complete profiles (has county and terms accepted)
     */
    public function scopeComplete($query)
    {
        return $query->whereNotNull('county_id')
                    ->where('terms_accepted', true);
    }

    /**
     * Check if profile is complete for basic access
     */
    public function isComplete(): bool
    {
        return !is_null($this->county_id) &&
               $this->terms_accepted;
    }
}
