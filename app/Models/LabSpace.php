<?php

namespace App\Models;

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
        'image_path',
        'safety_requirements',
        'is_available',
        'available_from',
        'available_until',
        'equipment_list',
        'rules',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_available' => 'boolean',
        'equipment_list' => 'array',
        'safety_requirements' => 'array',
        'available_from' => 'datetime:H:i',
        'available_until' => 'datetime:H:i',
    ];

    protected $appends = [
        'image_url',
        'type_name',
        'is_active',
        'amenities',
        'gallery_media',
    ];

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
     * Get all bookings for this lab space.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(LabBooking::class);
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
    public function featuredMedia()
    {
        return $this->media->firstWhere('pivot.role', 'featured');
    }

    /**
     * Get gallery media.
     */
    public function galleryMedia()
    {
        return $this->media->where('pivot.role', 'gallery');
    }

    /**
     * Set featured media by ID.
     */
    public function setFeaturedMedia(?int $mediaId): void
    {
        $this->media()->wherePivot('role', 'featured')->detach();
        if ($mediaId) {
            $this->media()->attach($mediaId, ['role' => 'featured']);
        }
    }

    /**
     * Add gallery media by IDs.
     */
    public function addGalleryMedia(array $mediaIds): void
    {
        foreach ($mediaIds as $id) {
            $this->media()->attach($id, ['role' => 'gallery']);
        }
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
     * Get the image URL for the lab space.
     * Prioritizes polymorphic featured media, falls back to legacy image_path.
     */
    public function getImageUrlAttribute(): ?string
    {
        // Check polymorphic featured media first
        $featured = $this->featuredMedia();
        if ($featured) {
            return $featured->url;
        }

        // Fallback to legacy image_path
        if (!$this->image_path) {
            return null;
        }

        // If it's already a full URL, return as-is
        if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
            return $this->image_path;
        }

    // Otherwise, generate storage URL
    return asset('storage/' . $this->image_path);
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
            default => ucfirst(str_replace('_', ' ', $this->type)),
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
        return $this->is_available;
    }

    /**
     * Frontend compatibility: Map equipment_list to amenities.
     */
    public function getAmenitiesAttribute(): array
    {
        return $this->equipment_list ?? [];
    }
}
