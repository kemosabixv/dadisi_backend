<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlugRedirect extends Model
{
    public $timestamps = false;

    protected $fillable = ['old_slug', 'new_slug', 'post_id', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Post relationship
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
