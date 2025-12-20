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
            'photo_url' => $this->photo_path ? url('storage/' . $this->photo_path) : null,
            'website_url' => $this->website_url,
            'linkedin_url' => $this->linkedin_url,
            'is_featured' => (bool) $this->is_featured,
        ];
    }
}
