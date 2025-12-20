<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_tag', 'tag_id', 'event_id');
    }
}
