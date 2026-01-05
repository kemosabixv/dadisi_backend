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
        return $this->profile_picture_path
            ? url('storage/' . $this->profile_picture_path)
            : null;
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
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: $this->username;
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profile_picture_url',
        'display_name',
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
        ];
    }
    /**
     * Check if user can access admin panel
     */
    public function canAccessAdminPanel(): bool
    {
        // Check if user has specific admin roles or is a staff member
        // Roles: super_admin, admin, finance, events_manager, content_editor, lab_manager
        return $this->hasAnyRole(['super_admin', 'admin', 'finance', 'events_manager', 'content_editor', 'lab_manager']) 
               || ($this->memberProfile && $this->memberProfile->is_staff);
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
     * Private messages sent by this user
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(PrivateMessage::class, 'sender_id');
    }

    /**
     * Private messages received by this user
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(PrivateMessage::class, 'recipient_id');
    }

    /**
     * Lab bookings made by this user
     */
    public function labBookings(): HasMany
    {
        return $this->hasMany(LabBooking::class);
    }

    /**
     * Get the default guard for role/permission checks.
     * For API requests with Sanctum authentication, use 'api' guard.
     * For web requests, use 'web' guard.
     */
    public function getDefaultGuardName(): string
    {
        // If we're in an API context (Sanctum token authentication), use 'api' guard
        if ($this->tokenCan('*') || auth('sanctum')->check()) {
            return 'api';
        }

        // Default to 'web' guard
        return $this->getGuardNames()->first() ?? 'web';
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
}
