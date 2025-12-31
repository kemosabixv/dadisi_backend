<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
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
            'slug' => $this->slug,
            'name' => $this->name,
            'status' => $this->status,
            'starts_at' => $this->trial_ends_at ? $this->trial_ends_at->toIso8601String() : $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at ? $this->ends_at->toIso8601String() : null,
            'trial_ends_at' => $this->trial_ends_at ? $this->trial_ends_at->toIso8601String() : null,
            'canceled_at' => $this->canceled_at ? $this->canceled_at->toIso8601String() : null,
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'days_remaining' => $this->daysRemainingUntilExpiry(),
            
            // Relationships
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'subscriber' => new UserResource($this->whenLoaded('subscriber')),
            'enhancements' => $this->whenLoaded('enhancements'), // Handle properly if needed
        ];
    }
}
