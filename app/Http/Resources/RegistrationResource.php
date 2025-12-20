<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => new EventResource($this->whenLoaded('event')),
            'user' => new UserResource($this->whenLoaded('user')),
            'ticket' => new TicketResource($this->whenLoaded('ticket')),
            'confirmation_code' => $this->confirmation_code,
            'status' => $this->status,
            'check_in_at' => $this->check_in_at,
            'waitlist_position' => $this->waitlist_position,
            'qr_code_token' => $this->when(($user = auth()->user()) && ($user->id === $this->user_id || $user->canAccessAdminPanel()), $this->qr_code_token),
            'qr_code_url' => $this->qr_code_path ? url('storage/' . $this->qr_code_path) : null,
            'created_at' => $this->created_at,
        ];
    }
}
