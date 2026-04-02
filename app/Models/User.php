<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
// use Laravel\Sanctum\HasApiTokens;
use Laragear\WebAuthn\WebAuthnAuthentication;
use Laravelcm\Subscriptions\Models\Subscription;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements WebAuthnAuthenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes, WebAuthnAuthentication;

    /**
     * Guard name for Spatie Permission.
     * Handled by getDefaultGuardName() to unified 'web' guard.
     */

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
        'active_subscription_id',
        'subscription_status',
        'subscription_activated_at',
        'last_payment_date',
        'profile_picture_path',
        'deletion_scheduled_at',
        'deletion_scheduled_for',
        'deletion_scheduled_reason',
    ];

    public function getProfilePictureUrlAttribute(): ?string
    {
        if (! $this->profile_picture_path) {
            return null;
        }

        // If path starts with blobs/, it's a CAS/R2 file
        if (str_starts_with($this->profile_picture_path, 'blobs/')) {
            return \Illuminate\Support\Facades\Storage::disk('r2')->url($this->profile_picture_path);
        }

        return url('storage/'.$this->profile_picture_path);
    }

    /**
     * Get the user's name, falling back to profile or username
     */
    public function getNameAttribute(): string
    {
        if ($this->attributes['name'] ?? null) {
            return $this->attributes['name'];
        }

        if ($this->memberProfile) {
            return $this->memberProfile->full_name;
        }

        return '';
    }

    /**
     * Get a display name for the user
     */
    /**
     * Get the name of the user's active plan
     */
    public function getActivePlanNameAttribute(): string
    {
        // Try to get from eager loaded relationship first
        $subscription = $this->activeSubscription()->first();

        return $subscription?->plan?->name ?? 'Free';
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: ($this->username ?: 'User');
    }

    /**
     * Check if the user has any registered WebAuthn credentials (passkeys).
     */
    public function getHasPasskeysAttribute(): bool
    {
        return $this->webAuthnCredentials()->exists();
    }

    /**
     * Check if the user has a local password set (for OAuth migration).
     */
    public function getHasPasswordAttribute(): bool
    {
        return !is_null($this->password);
    }

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'profile_picture_url',
        'display_name',
        'has_passkeys',
        'has_password',
    ];

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        // Cascade soft-delete to member profile when user is soft-deleted
        static::deleting(function (User $user) {
            if ($user->memberProfile) {
                $user->memberProfile->delete();
            }
        });
    }

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
     * Get the user profile (alias for memberProfile)
     */
    public function profile(): HasOne
    {
        return $this->memberProfile();
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
     * Get the user's active subscription (standardized to PlanSubscription)
     */
    public function subscription(): MorphOne
    {
        return $this->morphOne(PlanSubscription::class, 'subscriber')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            });
    }

    /**
     * Get all subscriptions for the user
     */
    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(PlanSubscription::class, 'subscriber', 'subscriber_type', 'subscriber_id');
    }

    /**
     * Get the user's active plan subscription
     */
    public function activeSubscription()
    {
        return $this->subscriptions()->where(function ($query) {
            $query->whereNull('ends_at')
                ->orWhere('ends_at', '>', now());
        })->whereNull('canceled_at');
    }

    /**
     * Get the user's active lab subscription (subscription with lab quota features).
     *
     * Returns subscription only if:
     * - Status is 'active'
     * - Not in grace period (ends_at is NULL or future)
     * - Not canceled
     * - Plan has lab_hours_monthly feature (VALIDATED)
     *
     * Returns null if user has no active lab subscription, is in grace period,
     * or plan doesn't have lab_hours_monthly feature.
     *
     * Grace period: After subscription anniversary (ends_at has passed), quota is NOT replenished
     * until user renews or upgrades to another plan with quota.
     */
    public function activeLabSubscription(): ?PlanSubscription
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->whereNull('canceled_at')
            ->where(function ($query) {
                // Exclude grace period: only active subscriptions on/before/at anniversary
                // Grace period starts AFTER anniversary, so include anniversary date (>=)
                $query->whereNull('ends_at')      // Indefinite subscription
                    ->orWhere('ends_at', '>=', now()->startOfDay());  // Include anniversary date, exclude after midnight
            })
            ->with(['plan.systemFeatures'])
            ->get()
            ->first(function ($subscription) {
                // Verify plan actually has lab_hours_monthly feature with value > 0
                $quotaHours = $subscription->plan?->getFeatureValue('lab_hours_monthly');

                return $quotaHours && (int) $quotaHours > 0;
            });
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
            ->latest('submitted_at');
    }

    /**
     * Get all the user's subscription enhancements
     */
    public function subscriptionEnhancements(): HasManyThrough
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
            'id' => 'integer',
            'active_subscription_id' => 'integer',
            'two_factor_enabled' => 'boolean',
        ];
    }

    /**
     * Check if user can access admin panel
     */
    public function canAccessAdminPanel(): bool
    {
        return $this->can('access_admin_panel');
    }

    /**
     * Check if user is a staff member
     */
    public function isStaffMember(): bool
    {
        return $this->canAccessAdminPanel();
    }

    /**
     * Check if user is an admin or staff member.
     */
    public function isAdmin(): bool
    {
        return \App\Support\AdminAccessResolver::canAccessAdmin($this);
    }

    /**
     * Event registrations by this user
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * Forum threads created by this user
     */
    public function forumThreads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }

    /**
     * Forum posts (replies) by this user
     */
    public function forumPosts(): HasMany
    {
        return $this->hasMany(ForumPost::class);
    }

    /**
     * User's public key for encrypted messaging
     */
    public function publicKey(): HasOne
    {
        return $this->hasOne(UserPublicKey::class);
    }

    /**
     * Chat messages sent by this user
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    /**
     * Chat messages received by this user
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'recipient_id');
    }

    /**
     * Conversations this user is part of.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_one_id')
            ->orWhere('user_two_id', $this->id);
    }

    /**
     * Lab bookings made by this user
     */
    public function labBookings(): HasMany
    {
        return $this->hasMany(LabBooking::class);
    }

    public function bookingSeries(): HasMany
    {
        return $this->hasMany(BookingSeries::class);
    }

    public function slotHolds(): HasMany
    {
        return $this->hasMany(SlotHold::class);
    }

    public function quotaCommitments(): HasMany
    {
        return $this->hasMany(QuotaCommitment::class);
    }

    /**
     * Lab spaces assigned to this supervisor
     */
    public function assignedLabSpaces(): BelongsToMany
    {
        return $this->belongsToMany(LabSpace::class, 'lab_assignments', 'user_id', 'lab_space_id')
            ->withTimestamps();
    }

    /**
     * Get the default guard for role/permission checks.
     * For API requests with Sanctum authentication, use 'api' guard.
     * For web requests, use 'web' guard.
     */
    public function getDefaultGuardName(): string
    {
        return 'web';
    }

    /**
     * Events organized by this user
     */
    public function organizedEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    /**
     * Donations made by this user
     */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /**
     * Event orders (ticket purchases) by this user
     */
    public function eventOrders(): HasMany
    {
        return $this->hasMany(EventOrder::class);
    }

    /**
     * Route notifications for the OneSignal channel.
     */
    public function routeNotificationForOneSignal(): ?string
    {
        // OneSignal uses external_id or player_id. We'll use the user ID.
        return (string) $this->id;
    }
}
