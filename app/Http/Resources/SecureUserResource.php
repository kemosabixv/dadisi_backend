<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\UIPermissionService;

class SecureUserResource extends JsonResource
{
    /**
     * Disable the default "data" wrapping for this resource.
     * 
     * @var string|null
     */
    public static $wrap = null;

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
            'two_factor_enabled' => (bool) $this->two_factor_enabled,
            'has_passkeys' => $this->has_passkeys,
            'has_password' => $this->has_password,
            
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
                    'can_access_admin' => $this->canAccessAdminPanel(),
                    'first_name' => $this->memberProfile->first_name,
                    'last_name' => $this->memberProfile->last_name,
                ];
            }),

            // Include roles for frontend RBAC logic
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                });
            }),
        ];
    }
}
