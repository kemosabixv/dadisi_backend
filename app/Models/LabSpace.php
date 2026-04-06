<?php

namespace App\Models;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabSpace extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'capacity',
        'county',
        'location',
        'type',
        // Migration: legacy image_path removed, now using CAS media_id
        'safety_requirements',
        'is_available',
        'available_from',
        'available_until',
        'equipment_list',
        'rules',
        'hourly_rate',
        'opens_at',
        'closes_at',
        'operating_days',
        'bookings_enabled',
        'checkin_token',
        'timezone',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_available' => 'boolean',
        'bookings_enabled' => 'boolean',
        'equipment_list' => 'array',
        'safety_requirements' => 'array',
        'available_from' => 'datetime:H:i',
        'available_until' => 'datetime:H:i',
        'hourly_rate' => 'float',
        'opens_at' => 'datetime:H:i',
        'closes_at' => 'datetime:H:i',
        'operating_days' => 'array',
    ];

    protected $appends = [
        'image_url',
        'type_name',
        'is_active',
        'amenities',
        'gallery_media',
        'computed_status',
    ];

    /**
     * Get the supervisors assigned to this lab space.
     */
    public function supervisors(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lab_assignments', 'lab_space_id', 'user_id')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    // ==================== Constants ====================

    public const TYPE_DRY_LAB = 'dry_lab';
    public const TYPE_WET_LAB = 'wet_lab';
    public const TYPE_GREENHOUSE = 'greenhouse';
    public const TYPE_MOBILE_LAB = 'mobile_lab';
    public const TYPE_MAKERSPACE = 'makerspace';
    public const TYPE_WORKSHOP = 'workshop';
    public const TYPE_STUDIO = 'studio';
    public const TYPE_OTHER = 'other';

    // ==================== Relationships ====================

    /**
     * Get all maintenance blocks for this lab space.
     */
    public function maintenanceBlocks(): HasMany
    {
        return $this->hasMany(LabMaintenanceBlock::class);
    }

    /**
     * Get all bookings for this lab space.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(LabBooking::class);
    }

    public function series(): HasMany
    {
        return $this->hasMany(BookingSeries::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(SlotHold::class);
    }

    public function closures(): HasMany
    {
        return $this->hasMany(LabClosure::class);
    }

    /**
     * Polymorphic media relationship via media_attachments pivot.
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'attachable', 'media_attachments')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get featured media.
     */
    public function featuredMedia(): ?Media
    {
        return $this->media()->wherePivot('role', 'featured')->first();
    }

    /**
     * Get gallery media.
     */
    public function galleryMedia()
    {
        return $this->media()->wherePivot('role', 'gallery');
    }

    /**
     * Set featured media by ID.
     */
    public function setFeaturedMedia(?int $mediaId): void
    {
        $old = $this->featuredMedia();
        if ($old) {
            $old->decrement('usage_count');
        }

        $this->media()->wherePivot('role', 'featured')->detach();
        if ($mediaId) {
            $this->media()->attach($mediaId, ['role' => 'featured']);
            Media::find($mediaId)?->increment('usage_count');
        }
    }

    /**
     * Add gallery media by IDs.
     */
    public function addGalleryMedia(array $mediaIds): void
    {
        foreach ($mediaIds as $id) {
            if (!$this->media()->wherePivot('role', 'gallery')->where('media_id', $id)->exists()) {
                $this->media()->attach($id, ['role' => 'gallery']);
                Media::find($id)?->increment('usage_count');
            }
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->checkin_token)) {
                $model->checkin_token = 'LAB-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(12));
            }
        });

        static::updating(function (self $lab) {
            if ($lab->isDirty('slug')) {
                $oldSlug = $lab->getOriginal('slug');
                $newSlug = $lab->slug;
                if ($oldSlug && $newSlug) {
                    app(\App\Services\Contracts\MediaServiceContract::class)->renameFolder(
                        auth()->user() ?? User::role('admin')->first(), // LabSpace might not have explicit creator ref in $fillable
                        'public', 
                        ['lab-spaces', $oldSlug], 
                        $newSlug
                    );
                }
            }
        });

        static::deleting(function (self $lab) {
            foreach ($lab->media as $media) {
                $media->decrement('usage_count');
            }
            $lab->media()->detach();
        });
    }

    // ==================== Scopes ====================

    /**
     * Scope to active lab spaces.
     */
    public function scopeActive($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope to available lab spaces (alias for active).
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope to filter by lab space type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to lab spaces in a specific county.
     */
    public function scopeForCounty($query, string $county)
    {
        return $query->where('county', $county);
    }

    /**
     * Scope to lab spaces with minimum capacity.
     */
    public function scopeWithCapacity($query, int $minCapacity)
    {
        return $query->where('capacity', '>=', $minCapacity);
    }

    // ==================== Accessors & Mutators ====================

    /**
     * Get the image URL for the lab space (CAS/R2 only).
     */
    public function getImageUrlAttribute(): ?string
    {
        $featuredMedia = $this->featuredMedia();
        return $featuredMedia ? $featuredMedia->url : null;
    }

/**
 * Get gallery media (for frontend compatibility).
 */
public function getGalleryMediaAttribute()
{
    return $this->media->where('pivot.role', 'gallery')->values();
}

    /**
     * Get human-readable type name.
     */
    public function getTypeNameAttribute(): string
    {
        return match($this->type) {
            self::TYPE_DRY_LAB => 'Dry Lab',
            self::TYPE_WET_LAB => 'Wet Lab',
            self::TYPE_GREENHOUSE => 'Greenhouse',
            self::TYPE_MOBILE_LAB => 'Mobile Lab',
            self::TYPE_MAKERSPACE => 'Makerspace',
            self::TYPE_WORKSHOP => 'Workshop',
            self::TYPE_STUDIO => 'Studio',
            self::TYPE_OTHER => 'Other',
            default => $this->type ? ucfirst(str_replace('_', ' ', (string) $this->type)) : 'Unknown',
        };
    }

    /**
     * Legacy compatibility: Map is_available to status attribute.
     * Returns 'active' or 'inactive' based on is_available.
     */
    public function getStatusAttribute(): string
    {
        return $this->is_available ? 'active' : 'inactive';
    }

    /**
     * Computed status derived from currently-active maintenance blocks.
     * Priority (highest first): closure > maintenance > holiday > open
     *
     * Values:
     *   'open'               – no active block
     *   'under_maintenance'  – maintenance block is active right now
     *   'holiday'            – holiday block is active right now
     *   'temporarily_closed' – closure block is active right now
     */
    public function getComputedStatusAttribute(): string
    {
        $activeBlock = $this->maintenanceBlocks()
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderByRaw("CASE 
                WHEN block_type = 'closure' THEN 1 
                WHEN block_type = 'maintenance' THEN 2 
                WHEN block_type = 'holiday' THEN 3 
                ELSE 4 
            END")
            ->first();

        if (!$activeBlock) {
            return 'open';
        }

        return match ($activeBlock->block_type) {
            'holiday'     => 'holiday',
            'closure'     => 'temporarily_closed',
            default       => 'under_maintenance',
        };
    }

    /**
     * Legacy compatibility: Allow setting is_available via status attribute.
     * Accepts 'active', 'inactive', or boolean values.
     */
    public function setStatusAttribute($value): void
    {
        if (is_bool($value)) {
            $this->attributes['is_available'] = $value;
        } elseif (is_string($value)) {
            $this->attributes['is_available'] = strtolower($value) === 'active';
        }
    }

    /**
     * Frontend compatibility: Map is_available to is_active.
     */
    public function getIsActiveAttribute(): bool
    {
        return (bool) ($this->is_available ?? false);
    }

    /**
     * Frontend compatibility: Map equipment_list to amenities.
     */
    public function getAmenitiesAttribute(): array
    {
        return $this->equipment_list ?? [];
    }

    // ==================== OPERATING HOURS COMPUTED PROPERTIES ====================

    /**
     * Calculate the available hours per month based on operating days and operating hours.
     * Example: 5 days/week × 8 hours/day × 4.33 weeks/month ≈ 173 hours/month
     *
     * @return int The average available hours per month
     */
    public function monthlyAvailableHours(): int
    {
        if (!$this->operating_days || empty($this->operating_days) || !$this->opens_at || !$this->closes_at) {
            return 0;
        }

        try {
            $operatingDaysPerWeek = is_array($this->operating_days) ? count($this->operating_days) : 0;

            if ($operatingDaysPerWeek === 0) {
                return 0;
            }

            $opensAt = $this->opens_at instanceof \Carbon\Carbon 
                ? $this->opens_at 
                : \Carbon\Carbon::createFromFormat('H:i', $this->opens_at);
            
            $closesAt = $this->closes_at instanceof \Carbon\Carbon 
                ? $this->closes_at 
                : \Carbon\Carbon::createFromFormat('H:i', $this->closes_at);

            $hoursPerDay = $closesAt->diffInHours($opensAt);

            if ($hoursPerDay <= 0) {
                return 0;
            }

            $weeksPerMonth = 4.33;
            $totalHours = (int) round($operatingDaysPerWeek * $weeksPerMonth * $hoursPerDay);

            return max(0, $totalHours);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Calculate the maximum monthly slots offered.
     * Example: 173 available hours × 2 slots/hour = 346 slots/month
     *
     * @return int The maximum number of slots offered per month
     */
    public function maxMonthlySlotsOffered(): int
    {
        return $this->monthlyAvailableHours() * $this->capacity;
    }

    // ============================================================================
}
