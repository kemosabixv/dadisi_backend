<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'parent_id' => $this->parent_id,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
