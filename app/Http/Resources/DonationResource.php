<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Donation
 */
class DonationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get payment method from associated payment
        $payment = $this->relationLoaded('payment') ? $this->payment : (\App\Models\Payment::find($this->payment_id));
        $paymentMethod = $payment?->meta['payment_method'] ?? null;
        
        $receiptUrl = ($this->status === 'paid' && $this->receipt_number) 
            ? config('app.url') . "/donations/receipt/{$this->reference}"
            : null;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'donor_name' => $this->donor_name,
            'donor_email' => $this->donor_email,
            'donor_phone' => $this->donor_phone,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_method' => $paymentMethod,
            'receipt_number' => $this->receipt_number,
            'receipt_url' => $receiptUrl,
            'notes' => $this->notes,
            'county' => $this->whenLoaded('county', fn() => [
                'id' => $this->county->id,
                'name' => $this->county->name,
            ]),
            'campaign' => $this->whenLoaded('campaign', fn() => [
                'id' => $this->campaign->id,
                'title' => $this->campaign->title,
                'slug' => $this->campaign->slug,
            ]),
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
