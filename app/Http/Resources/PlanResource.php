<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
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
            'description' => $this->description,
            'is_active' => (bool)$this->is_active,
            'price' => (float)$this->price,
            'currency' => $this->currency,
            'base_monthly_price' => (float)$this->base_monthly_price,
            'yearly_price' => (float)$this->yearly_price,
            'yearly_discount_percent' => (float)$this->yearly_discount_percent,
            'invoice_period' => $this->invoice_period,
            'invoice_interval' => $this->invoice_interval,
            'trial_period' => $this->trial_period,
            'trial_interval' => $this->trial_interval,
            'sort_order' => $this->sort_order,
            
            // Appended attributes from model
            'pricing' => $this->pricing,
            'promotions' => $this->promotions,
            'savings_percent' => $this->savings_percent,
            
            // Relationships
            'features' => $this->whenLoaded('systemFeatures', function() {
                return $this->systemFeatures->map(function($feature) {
                    return [
                        'id' => $feature->id,
                        'name' => $feature->name,
                        'slug' => $feature->slug,
                        'value' => $feature->pivot->value,
                        'display_name' => $feature->pivot->display_name ?? $feature->name,
                        'display_description' => $feature->pivot->display_description ?? $feature->description,
                    ];
                });
            }),
        ];
    }
}
