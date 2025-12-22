<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LabSpace extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'capacity',
        'image_path',
        'amenities',
        'safety_requirements',
        'hourly_rate',
        'is_active',
    ];

    protected $casts = [
        'amenities' => 'array',
        'safety_requirements' => 'array',
        'is_active' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'capacity' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ==================== Relationships ====================

    /**
     * Get all bookings for this lab space.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(LabBooking::class);
    }

    /**
     * Get all maintenance blocks for this lab space.
     */
    public function maintenanceBlocks(): HasMany
    {
        return $this->hasMany(LabMaintenanceBlock::class);
    }

    // ==================== Scopes ====================

    /**
     * Scope to only active lab spaces.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ==================== Accessors ====================

    /**
     * Get the image URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        // Check if it's already a full URL
        if (Str::startsWith($this->image_path, ['http://', 'https://'])) {
            return $this->image_path;
        }

        return asset('storage/' . $this->image_path);
    }

    /**
     * Get human-readable type name.
     */
    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            'wet_lab' => 'Wet Lab',
            'dry_lab' => 'Dry Lab',
            'greenhouse' => 'Greenhouse',
            'mobile_lab' => 'Mobile Lab',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }
}
