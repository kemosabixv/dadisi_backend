<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DonationCampaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'short_description',
        'goal_amount',
        'minimum_amount',
        'currency',
        'status',
        'county_id',
        'created_by',
        'current_amount',
        'donor_count',
        'starts_at',
        'ends_at',
        'published_at',
    ];

    protected $casts = [
        'goal_amount' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected $appends = [
        'current_amount',
        'progress_percentage',
        'donor_count',
        'is_goal_reached',
        'hero_image_url',
    ];

    /**
     * Get the creator of the campaign.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the county for this campaign.
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /**
     * Get all donations for this campaign.
     */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class, 'campaign_id');
    }

    /**
     * Get the total amount donated to this campaign.
     * Fallback to dynamic calculation if the column is null (safety).
     */
    public function getCurrentAmountAttribute($value): float
    {
        if ($value !== null) {
            return (float) $value;
        }

        return (float) $this->donations()->where('status', 'paid')->sum('amount');
    }

    /**
     * Get the progress percentage toward the goal.
     */
    public function getProgressPercentageAttribute(): float
    {
        if (! $this->goal_amount || $this->goal_amount <= 0) {
            return 0;
        }

        $percentage = ($this->current_amount / $this->goal_amount) * 100;

        return min(100, round($percentage, 2));
    }

    /**
     * Get the number of unique donors.
     * Fallback to dynamic calculation if the column is null (safety).
     */
    public function getDonorCountAttribute($value): int
    {
        if ($value !== null) {
            return (int) $value;
        }

        return $this->donations()
            ->where('status', 'paid')
            ->distinct('donor_email')
            ->count('donor_email');
    }

    /**
     * Check if the goal has been reached.
     */
    public function getIsGoalReachedAttribute(): bool
    {
        if (! $this->goal_amount || $this->goal_amount <= 0) {
            return false;
        }

        return $this->current_amount >= $this->goal_amount;
    }

    /**
     * Get the full URL for the hero image (CAS/R2 only).
     */
    public function getHeroImageUrlAttribute(): ?string
    {
        $featuredMedia = $this->featuredMedia();

        return $featuredMedia ? $featuredMedia->url : null;
    }

    /**
     * Media relationship (attached images via polymorphic pivot)
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'attachable', 'media_attachments')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the featured image media (role = 'featured')
     */
    public function featuredMedia(): ?Media
    {
        return $this->media()->wherePivot('role', 'featured')->first();
    }

    /**
     * Get gallery images (role = 'gallery')
     */
    public function galleryMedia()
    {
        return $this->media()->wherePivot('role', 'gallery');
    }

    /**
     * Attach media as featured image (replaces existing featured)
     */
    public function setFeaturedMedia(int $mediaId): void
    {
        $old = $this->featuredMedia();
        if ($old) {
            $old->decrement('usage_count');
        }

        $this->media()->wherePivot('role', 'featured')->detach();
        $this->media()->attach($mediaId, ['role' => 'featured']);
        Media::find($mediaId)?->increment('usage_count');
    }

    /**
     * Attach media as gallery images
     */
    public function addGalleryMedia(array $mediaIds): void
    {
        foreach ($mediaIds as $id) {
            if (! $this->media()->wherePivot('role', 'gallery')->where('media_id', $id)->exists()) {
                $this->media()->attach($id, ['role' => 'gallery']);
                Media::find($id)?->increment('usage_count');
            }
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = static::generateUniqueSlug($model->title);
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('title') && ! $model->isDirty('slug')) {
                $model->slug = static::generateUniqueSlug($model->title, $model->id);
            }

            // Handle slug change/folder rename
            if ($model->isDirty('slug')) {
                $oldSlug = $model->getOriginal('slug');
                $newSlug = $model->slug;
                if ($oldSlug && $newSlug) {
                    app(\App\Services\Contracts\MediaServiceContract::class)->renameFolder(
                        $model->creator ?? auth()->user() ?? User::find($model->created_by),
                        'public',
                        ['campaigns', $oldSlug],
                        $newSlug
                    );
                }
            }
        });

        static::deleting(function (self $campaign) {
            foreach ($campaign->media as $media) {
                $media->decrement('usage_count');
            }
            $campaign->media()->detach();
        });
    }

    /**
     * Scope: get active campaigns.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: get published campaigns.
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('status', 'active');
    }

    /**
     * Scope: get ongoing campaigns (started and not ended).
     */
    public function scopeOngoing($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now());
        })->where(function ($q) {
            $q->whereNull('ends_at')
                ->orWhere('ends_at', '>=', now());
        });
    }

    /**
     * Scope: get upcoming campaigns.
     */
    public function scopeUpcoming($query)
    {
        return $query->whereNotNull('starts_at')
            ->where('starts_at', '>', now());
    }

    /**
     * Scope: filter by county.
     */
    public function scopeByCounty($query, $countyId)
    {
        return $query->where('county_id', $countyId);
    }

    /**
     * Check if minimum amount should be enforced.
     * Bypassed in local and staging environments.
     */
    public function shouldEnforceMinimumAmount(): bool
    {
        $env = app()->environment();

        return ! in_array($env, ['local', 'staging', 'testing']);
    }

    /**
     * Get the effective minimum amount (null if bypassed).
     */
    public function getEffectiveMinimumAmount(): ?float
    {
        if (! $this->shouldEnforceMinimumAmount()) {
            return null;
        }

        return $this->minimum_amount;
    }

    /**
     * Generate a unique slug from the title.
     */
    public static function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        $query = static::withTrashed()->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
            $query = static::withTrashed()->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
