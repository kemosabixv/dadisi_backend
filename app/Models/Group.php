<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'county_id',
        'image_path',
        'member_count',
        'is_private',
        'is_active',
    ];

    protected $casts = [
        'member_count' => 'integer',
        'is_private' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the county associated with this group.
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /**
     * Get all members of this group.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get the forum threads associated with this group.
     */
    public function forumThreads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }

    /**
     * Get the group memberships.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Check if a user is a member of this group.
     */
    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Scope to filter active groups.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by county.
     */
    public function scopeForCounty($query, int $countyId)
    {
        return $query->where('county_id', $countyId);
    }

    /**
     * Update the member count.
     */
    public function updateMemberCount(): void
    {
        $this->update(['member_count' => $this->members()->count()]);
    }
}
