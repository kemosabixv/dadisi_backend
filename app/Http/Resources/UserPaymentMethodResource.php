<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPaymentMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'identifier' => $this->identifier,
            'label' => $this->label,
            'is_primary' => (bool)$this->is_primary,
            'is_active' => (bool)$this->is_active,
            'data' => $this->data,
            'created_at' => $this->created_at->toIso8601String(),
            
            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
