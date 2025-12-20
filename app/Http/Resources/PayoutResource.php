<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'organizer_id' => $this->organizer_id,
            'total_revenue' => (float) $this->total_amount,
            'commission_amount' => (float) $this->commission_amount,
            'net_payout' => (float) $this->payout_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'hold_until' => $this->hold_until,
            'reference' => $this->reference,
            'admin_notes' => $this->when(auth()->user() && auth()->user()->canAccessAdminPanel(), $this->admin_notes),
            'created_at' => $this->created_at,
        ];
    }
}
