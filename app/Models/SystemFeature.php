<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * System Feature Model
 * 
 * Represents built-in features that can be associated with subscription plans.
 * These features cannot be deleted by users - they are seeded and managed by the system.
 */
class SystemFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'value_type',
        'default_value',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get all plans that have this feature enabled.
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_system_feature')
            ->withPivot(['value', 'display_name', 'display_description'])
            ->withTimestamps();
    }

    /**
     * Scope to get only active features.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get features sorted by sort_order.
     */
    public function scopeSorted($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Check if this feature uses numeric values.
     */
    public function isNumeric(): bool
    {
        return $this->value_type === 'number';
    }

    /**
     * Check if this feature uses boolean values.
     */
    public function isBoolean(): bool
    {
        return $this->value_type === 'boolean';
    }

    /**
     * Cast the default value to the appropriate type.
     */
    public function getTypedDefaultValue()
    {
        if ($this->isBoolean()) {
            return filter_var($this->default_value, FILTER_VALIDATE_BOOLEAN);
        }
        
        if ($this->isNumeric()) {
            return (int) $this->default_value;
        }

        return $this->default_value;
    }
}
