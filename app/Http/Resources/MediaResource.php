<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'type' => $this->type,
            'mime_type' => $this->mime_type,
            'size' => $this->file_size, // Rename to size for frontend
            'visibility' => $this->visibility,
            'share_token' => $this->share_token,
            'allow_download' => (bool) $this->allow_download,
            'is_public' => $this->visibility === 'public',
            'attached_to' => $this->attached_to,
            'attached_to_id' => $this->attached_to_id,
            'url' => url('/storage' . $this->file_path),
            'original_url' => url('/storage' . $this->file_path), // Add original_url for frontend
            'owner' => [
                'id' => $this->user_id,
                'name' => $this->owner?->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
