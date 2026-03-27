<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'payer_id' => $this->payer_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_method' => $this->method,
            'reference' => $this->reference,
            'transaction_id' => $this->transaction_id,
            'external_reference' => $this->external_reference,
            'description' => $this->description,
            'county' => $this->county,
            'payable_type' => $this->payable_type,
            'payable_id' => $this->payable_id,
            'paid_at' => $this->paid_at ? $this->paid_at->toIso8601String() : null,
            'receipt_url' => $this->receipt_url ?? (function() {
                if ($this->status !== 'paid' && $this->status !== 'completed') return null;
                
                return match($this->payable_type) {
                    'donation' => config('app.frontend_url') . "/donations/receipt/{$this->reference}",
                    'subscription' => config('app.frontend_url') . "/dashboard/subscription/receipt/{$this->reference}",
                    default => null
                };
            })(),
            'refunded_at' => $this->refunded_at ? $this->refunded_at->toIso8601String() : null,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'metadata' => $this->metadata,

            // Relationships
            'payer' => new UserResource($this->whenLoaded('payer')),
        ];
    }
}
