<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    use HasFactory;
    protected $table = 'media_files';

    protected $fillable = [
        'hash',
        'disk',
        'path',
        'size',
        'mime_type',
        'ref_count',
    ];

    protected $casts = [
        'size' => 'integer',
        'ref_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Virtual media instances pointing to this physical file
     */
    public function virtualMedia(): HasMany
    {
        return $this->hasMany(Media::class, 'media_file_id');
    }

    /**
     * Get the full URL from the storage disk
     */
    public function getUrl(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
