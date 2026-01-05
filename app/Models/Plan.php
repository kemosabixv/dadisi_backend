<?php

namespace App\Models;

use App\Services\Contracts\ExchangeRateServiceContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravelcm\Subscriptions\Models\Plan as BasePlan;

class Plan extends BasePlan
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_active',
        'requires_student_approval',
        'price',
        'signup_fee',
        'currency',
        'trial_period',
        'trial_interval',
        'invoice_period',
        'invoice_interval',
        'grace_period',
        'grace_interval',
        'prorate_day',
        'prorate_period',
        'prorate_extend_due',
        'active_subscribers_limit',
        'sort_order',
        'type',
        'base_monthly_price',
        'yearly_discount_percent',
        'default_billing_period',
        'monthly_promotion_discount_percent',
        'monthly_promotion_expires_at',
        'yearly_promotion_discount_percent',
        'yearly_promotion_expires_at',
    ];

    /**
     * Override setAttribute to filter out invalid 'active' column
     * (vendor package may try to set it, but our schema uses 'is_active')
     */
    public function setAttribute($key, $value)
    {
        // Skip the vendor package's 'active' attribute; use 'is_active' instead
        if ($key === 'active' && ! isset($this->attributes['active'])) {
            $key = 'is_active';
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Expose an `active` accessor for compatibility with tests.
     */
    public function getActiveAttribute(): bool
    {
        if (array_key_exists('is_active', $this->attributes)) {
            return (bool) $this->attributes['is_active'];
        }

        return (bool) ($this->attributes['active'] ?? false);
    }

    /**
     * Scope to return only active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the default free plan for new registrations.
     * Returns the first active plan with price = 0 (free tier).
     */
    public static function getDefaultFreePlan(): ?self
    {
        return static::where('is_active', true)
            ->where(function ($query) {
                $query->where('base_monthly_price', '<=', 0)
                    ->orWhere('price', '<=', 0);
            })
            ->orderBy('sort_order')
            ->first();
    }

    protected $casts = [
        'base_monthly_price' => 'decimal:2',
        'yearly_discount_percent' => 'decimal:2',
        'monthly_promotion_discount_percent' => 'decimal:2',
        'monthly_promotion_expires_at' => 'datetime',
        'yearly_promotion_discount_percent' => 'decimal:2',
        'yearly_promotion_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'trial_period' => 'integer',
        'invoice_period' => 'integer',
        'grace_period' => 'integer',
        'sort_order' => 'integer',
        'prorate_day' => 'integer',
        'prorate_period' => 'integer',
        'prorate_extend_due' => 'integer',
    ];

    /**
     * Get the system features associated with this plan.
     */
    public function systemFeatures(): BelongsToMany
    {
        return $this->belongsToMany(SystemFeature::class, 'plan_system_feature')
            ->withPivot(['value', 'display_name', 'display_description'])
            ->withTimestamps();
    }

    /**
     * Get the value of a system feature for this plan.
     * Returns the feature's default value if not associated with this plan.
     *
     * @param  string  $slug  The feature slug
     * @param  mixed  $default  Default value if feature not found
     * @return mixed The feature value (typed appropriately)
     */
    public function getFeatureValue(string $slug, $default = null)
    {
        $feature = $this->systemFeatures()->where('slug', $slug)->first();

        if ($feature) {
            $value = $feature->pivot->value;

            // Type cast based on feature type
            if ($feature->value_type === 'boolean') {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            if ($feature->value_type === 'number') {
                return (int) $value;
            }

            return $value;
        }

        // Fall back to system feature's default value if it exists
        $systemFeature = SystemFeature::where('slug', $slug)->first();
        if ($systemFeature) {
            return $systemFeature->getTypedDefaultValue();
        }

        return $default;
    }

    /**
     * Check if this plan has a specific feature enabled.
     *
     * @param  string  $slug  The feature slug
     */
    public function hasFeature(string $slug): bool
    {
        return $this->systemFeatures()->where('slug', $slug)->exists();
    }

    /**
     * Get the display name for a feature on this plan.
     * Falls back to the feature's default name if not customized.
     *
     * @param  string  $slug  The feature slug
     */
    public function getFeatureDisplayName(string $slug): ?string
    {
        $feature = $this->systemFeatures()->where('slug', $slug)->first();

        if ($feature) {
            return $feature->pivot->display_name ?? $feature->name;
        }

        return null;
    }

    /**
     * Get the calculated yearly price (base_monthly_price * 12, promotions applied separately)
     */
    public function getYearlyPriceAttribute()
    {
        if (! $this->base_monthly_price) {
            return null;
        }

        // Return pure base yearly price (promotions handled separately)
        return $this->base_monthly_price * 12;
    }

    /**
     * Get the savings percentage for yearly billing (from promotions only)
     */
    public function getSavingsPercentAttribute()
    {
        if (! $this->isYearlyPromotionActive()) {
            return 0;
        }

        $baseYearly = $this->base_monthly_price * 12;
        $discountedYearly = $this->getEffectiveYearlyPrice();
        $savings = ($baseYearly - $discountedYearly) / $baseYearly * 100;

        return round($savings, 1);
    }

    /**
     * Check if monthly promotion is currently active
     */
    public function isMonthlyPromotionActive(): bool
    {
        return $this->monthly_promotion_discount_percent > 0 &&
               $this->monthly_promotion_expires_at &&
               now()->isBefore($this->monthly_promotion_expires_at);
    }

    /**
     * Check if yearly promotion is currently active
     */
    public function isYearlyPromotionActive(): bool
    {
        return $this->yearly_promotion_discount_percent > 0 &&
               $this->yearly_promotion_expires_at &&
               now()->isBefore($this->yearly_promotion_expires_at);
    }

    /**
     * Get effective monthly price (after promotion discount)
     */
    public function getEffectiveMonthlyPrice(): float
    {
        if ($this->isMonthlyPromotionActive()) {
            return (float) ($this->base_monthly_price * (1 - $this->monthly_promotion_discount_percent / 100));
        }

        return (float) $this->base_monthly_price;
    }

    /**
     * Get effective yearly price (after promotion discount)
     */
    public function getEffectiveYearlyPrice(): float
    {
        if ($this->isYearlyPromotionActive()) {
            return (float) (($this->base_monthly_price * 12) * (1 - $this->yearly_promotion_discount_percent / 100));
        }

        return $this->yearly_price ?? ($this->base_monthly_price * 12);
    }

    /**
     * Get pricing information in both currencies (including promotions)
     */
    public function getPricingAttribute()
    {
        $currencyService = app(ExchangeRateServiceContract::class);

        // Get effective prices (after promotions)
        $effectiveMonthlyKsh = $this->getEffectiveMonthlyPrice();
        $effectiveYearlyKsh = $this->getEffectiveYearlyPrice();

        return [
            'kes' => [
                'base_monthly' => (float) $this->base_monthly_price,
                'discounted_monthly' => (float) $effectiveMonthlyKsh,
                'base_yearly' => (float) $this->yearly_price,
                'discounted_yearly' => (float) $effectiveYearlyKsh,
            ],
            'usd' => [
                'base_monthly' => $currencyService->kesToUSD($this->base_monthly_price ?? 0),
                'discounted_monthly' => $currencyService->kesToUSD($effectiveMonthlyKsh),
                'base_yearly' => $currencyService->kesToUSD($this->yearly_price ?? 0),
                'discounted_yearly' => $currencyService->kesToUSD($effectiveYearlyKsh),
            ],
            'exchange_rate' => $currencyService->getCurrentUSDRate(),
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * Get promotions information
     */
    public function getPromotionsAttribute()
    {
        return [
            'monthly' => $this->monthly_promotion_discount_percent > 0 ? [
                'discount_percent' => (float) $this->monthly_promotion_discount_percent,
                'expires_at' => $this->monthly_promotion_expires_at?->toISOString(),
                'active' => $this->isMonthlyPromotionActive(),
                'time_remaining' => $this->monthly_promotion_expires_at ?
                    max(0, now()->diffInDays($this->monthly_promotion_expires_at)).' days' :
                    null,
            ] : null,
            'yearly' => $this->yearly_promotion_discount_percent > 0 ? [
                'discount_percent' => (float) $this->yearly_promotion_discount_percent,
                'expires_at' => $this->yearly_promotion_expires_at?->toISOString(),
                'active' => $this->isYearlyPromotionActive(),
                'time_remaining' => $this->yearly_promotion_expires_at ?
                    max(0, now()->diffInDays($this->yearly_promotion_expires_at)).' days' :
                    null,
            ] : null,
        ];
    }

    /**
     * Get display price for billing interval and currency
     */
    public function getPriceForInterval($interval = 'monthly', $currency = 'KES')
    {
        $amount = $interval === 'yearly' ? $this->yearly_price : $this->base_monthly_price;

        if ($currency === 'USD') {
            $currencyService = app(ExchangeRateServiceContract::class);

            return $currencyService->kesToUSD($amount ?? 0);
        }

        return $amount;
    }

    /**
     * Format price for display
     */
    public function formatPrice($interval = 'monthly', $currency = 'KES')
    {
        $amount = $this->getPriceForInterval($interval, $currency);
        $currencyService = app(ExchangeRateServiceContract::class);

        return $currencyService->formatAmount($amount, $currency);
    }
}
