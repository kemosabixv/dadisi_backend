<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravelcm\Subscriptions\Models\Subscription as BaseSubscription;
use App\Models\User;

class PlanSubscription extends BaseSubscription
{
    /**
     * Provide a sensible default for the required JSON `name` column so
     * tests do not fail when using SQLite in-memory databases.
     */
    protected $attributes = [
        'name' => '{"default":"Subscription"}',
    ];

    /**
     * Mass-assignable attributes
     */
    protected $fillable = [
        'subscriber_id',
        'subscriber_type',
        'plan_id',
        'name',
        'slug',
        'description',
        'timezone',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'cancels_at',
        'canceled_at',
        'status',
        'user_id',
        'expires_at',
    ];

    /**
     * Columns that should be visible when serializing to JSON
     */
    protected $visible = [
        'id',
        'subscriber_type',
        'subscriber_id',
        'plan_id',
        'name',
        'slug',
        'description',
        'timezone',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'cancels_at',
        'canceled_at',
        'created_at',
        'updated_at',
        'deleted_at',
        'status',
        'user_id',
        'expires_at',
        'plan',
    ];

    /**
     * Get the subscription enhancements
     *
     * This relationship provides enhanced subscription management features
     * like payment failure handling, grace periods, and renewal tracking.
     */
    public function enhancements(): HasMany
    {
        return $this->hasMany(SubscriptionEnhancement::class, 'subscription_id');
    }

    /**
     * Backwards-compatible alias for singular `enhancement()` calls.
     * Some older code or packages may call `enhancement()` expecting a
     * relation builder; provide a thin wrapper that returns the same
     * HasMany relation as `enhancements()`.
     */
    public function enhancement(): HasMany
    {
        return $this->enhancements();
    }

    /**
     * Get the plan associated with the subscription.
     * Overridden to ensure we get the App\Models\Plan instance.
     */
    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /* Compatibility wrappers for legacy method names used in tests */
    public function isActive(): bool
    {
        return $this->active();
    }

    public function isExpired(): bool
    {
        return $this->ended();
    }

    public function daysRemainingUntilExpiry(): int
    {
        if (! $this->ends_at) {
            return PHP_INT_MAX;
        }

        $seconds = $this->ends_at->getTimestamp() - now()->getTimestamp();
        $days = (int) floor($seconds / 86400);

        return (int) max(0, $days);
    }

    public function cancel(bool $immediately = false): \Laravelcm\Subscriptions\Models\Subscription
    {
        // Keep compatibility: set a status if model has the attribute
        try {
            if (array_key_exists('status', $this->attributes) || $this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), 'status')) {
                $this->status = 'cancelled';
            }
        } catch (\Exception $e) {
            // ignore schema inspection errors during tests
        }

        return parent::cancel($immediately);
    }

    /**
     * Provide a virtual status attribute for legacy tests when the
     * underlying `status` column does not exist.
     */
    public function getStatusAttribute($value)
    {
        if (! is_null($value)) {
            return $value;
        }

        if ($this->canceled()) {
            return 'cancelled';
        }

        if ($this->active()) {
            return 'active';
        }

        if ($this->ended()) {
            return 'expired';
        }

        return null;
    }

    /**
     * Ensure subscriber_type is set when creating with legacy attributes.
     */
    protected static function booted(): void
    {
        static::creating(function ($subscription) {
            if (empty($subscription->subscriber_type) && !empty($subscription->subscriber_id)) {
                $subscription->subscriber_type = User::class;
            }
        });
    }

    /**
     * Backwards-compatible alias for the package's morphTo relation `subscriber`.
     * Some parts of the application/tests expect a `user` relationship on the
     * subscription model; provide a convenience alias to avoid RelationNotFound
     * exceptions.
     */
    /**
     * Backwards-compatible alias for the package's morphTo relation `subscriber`.
     * Some parts of the application/tests expect a `user` relationship on the
     * subscription model; provide a convenience alias to avoid RelationNotFound
     * exceptions.
     */
    public function user()
    {
        return $this->morphTo('subscriber');
    }
}
