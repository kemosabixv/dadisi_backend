<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class County extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * Posts relationship (backwards compatibility for tests)
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'county_id');
    }
}
