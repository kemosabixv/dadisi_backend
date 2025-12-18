<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\UIPermissionService;

class SecureUserResource extends JsonResource
{
    public function toArray($request)
    {
        $uiPermissionService = new UIPermissionService($this->resource);
        
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Expose UI capabilities
            'ui_permissions' => $uiPermissionService->getUIPermissions(),
            
            // Admin-specific data
            'admin_access' => [
                'can_access_admin' => $this->canAccessAdminPanel(),
                'menu' => $uiPermissionService->getAuthorizedMenu(),
            ],
            
            // Basic profile data - limit what is exposed
            'member_profile' => $this->whenLoaded('memberProfile', function () {
                return [
                    'is_staff' => (bool) $this->memberProfile->is_staff,
                    'first_name' => $this->memberProfile->first_name,
                    'last_name' => $this->memberProfile->last_name,
                ];
            }),
        ];
    }
}
