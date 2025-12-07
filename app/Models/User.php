<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Laravelcm\Subscriptions\Models\Subscription;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'google_id',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the user profile
     */
    public function memberProfile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    /**
     * Blog posts authored by user
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Media uploaded by user
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Get member profile if exists
     */
    public function getProfile(): ?MemberProfile
    {
        return $this->memberProfile;
    }

    /**
     * Check if user has completed their profile
     */
    public function hasCompleteProfile(): bool
    {
        return $this->memberProfile && $this->memberProfile->isComplete();
    }

    /**
     * Get the user's active subscription
     */
    public function subscription(): MorphOne
    {
        return $this->morphOne(Subscription::class, 'subscriber')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            });
    }

    /**
     * Get the user's active plan subscription
     */
    public function activeSubscription(): BelongsTo
    {
        return $this->belongsTo(PlanSubscription::class, 'active_subscription_id');
    }

    /**
     * Get the user's current plan
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the user's renewal preferences
     */
    public function renewalPreferences(): HasOne
    {
        return $this->hasOne(RenewalPreference::class);
    }

    /**
     * Get the user's student approval requests
     */
    public function studentApprovalRequests(): HasMany
    {
        return $this->hasMany(StudentApprovalRequest::class);
    }

    /**
     * Get the user's active student approval request
     */
    public function activeStudentApprovalRequest(): HasOne
    {
        return $this->hasOne(StudentApprovalRequest::class)
            ->where('status', 'pending')
            ->latest('requested_at');
    }

    /**
     * Get all the user's subscription enhancements
     */
    public function subscriptionEnhancements(): HasMany
    {
        return $this->hasManyThrough(
            SubscriptionEnhancement::class,
            PlanSubscription::class,
            'user_id',
            'subscription_id'
        );
    }

    /**
     * Check if user has active subscription
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription_status === 'active' &&
               ($this->subscription_expires_at === null || $this->subscription_expires_at->isFuture());
    }

    /**
     * Get or create renewal preferences for user
     */
    public function getOrCreateRenewalPreferences(): RenewalPreference
    {
        return $this->renewalPreferences()->firstOrCreate([
            'user_id' => $this->id,
        ], [
            'renewal_type' => 'automatic',
            'send_renewal_reminders' => true,
            'reminder_days_before' => 7,
        ]);
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
