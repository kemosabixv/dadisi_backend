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
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
            'transaction_id' => $this->transaction_id,
            'external_reference' => $this->external_reference,
            'description' => $this->description,
            'county' => $this->county,
            'payable_type' => $this->payable_type,
            'payable_id' => $this->payable_id,
            'paid_at' => $this->paid_at ? $this->paid_at->toIso8601String() : null,
            'refunded_at' => $this->refunded_at ? $this->refunded_at->toIso8601String() : null,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'metadata' => $this->metadata,
            
            // Relationships
            'payer' => new UserResource($this->whenLoaded('payer')),
        ];
    }
}
