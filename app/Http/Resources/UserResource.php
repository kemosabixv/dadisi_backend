<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\AdminAccessResolver;

class UserResource extends JsonResource
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
            'username' => $this->username,
            'email' => $this->email,

            // Minimal profile data required for UI logic
            'member_profile' => [
                'is_staff' => (bool) optional($this->memberProfile)->is_staff,
            ],

            // Computed capabilities for UI
            'ui_permissions' => [
                'can_access_admin' => AdminAccessResolver::canAccessAdmin($this->resource),
            ],
        ];
    }
}
