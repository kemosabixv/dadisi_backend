<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpeakerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'designation' => $this->designation,
            'bio' => $this->bio,
            'photo_path' => $this->photo_path,
            'photo_url' => $this->photo_url,
            'photo_media_id' => $this->media->firstWhere('pivot.role', 'speaker_photo')?->id,
            'photo_media' => $this->when($this->media->firstWhere('pivot.role', 'speaker_photo'), function () {
                $media = $this->media->firstWhere('pivot.role', 'speaker_photo');
                return [
                    'id' => $media->id,
                    'url' => $media->url,
                    'file_name' => $media->file_name,
                ];
            }),
            'website_url' => $this->website_url,
            'linkedin_url' => $this->linkedin_url,
            'is_featured' => (bool) $this->is_featured,
        ];
    }
}
