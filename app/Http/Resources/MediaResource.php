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
            'file_size' => $this->file_size,
            'is_public' => (bool) $this->is_public,
            'attached_to' => $this->attached_to,
            'attached_to_id' => $this->attached_to_id,
            'url' => url('/storage' . $this->file_path),
            'owner' => [
                'id' => $this->user_id,
                'name' => $this->owner?->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
