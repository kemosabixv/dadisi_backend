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
        $requestUser = $request->user('sanctum');
        $isOwner = $requestUser && $requestUser->id === $this->id;
        $isAdmin = $requestUser && AdminAccessResolver::canAccessAdmin($requestUser);
        $canViewPII = $isOwner || $isAdmin;

        $isStaff = $this->relationLoaded('roles') 
            ? AdminAccessResolver::canAccessAdmin($this->resource)
            : false;
        
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->when($canViewPII, $this->email),
            'email_verified_at' => $this->when($canViewPII, $this->email_verified_at),
            
            // Relation-aware display name to prevent N+1
            'display_name' => $this->relationLoaded('memberProfile') 
                ? ($this->memberProfile->full_name ?: $this->username)
                : $this->username,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($isAdmin, $this->deleted_at),
            'profile_picture_url' => $this->profile_picture_url,

            // Roles for display
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ]);
            }),

            // Full profile data with strict loading and PII guards
            'member_profile' => $this->whenLoaded('memberProfile', function () use ($canViewPII) {
                $profile = $this->memberProfile;
                return [
                    'id' => $profile->id,
                    'first_name' => $profile->first_name,
                    'last_name' => $profile->last_name,
                    'phone_number' => $this->when($canViewPII, $profile->phone_number),
                    'date_of_birth' => $this->when($canViewPII, $profile->date_of_birth?->format('Y-m-d')),
                    'gender' => $this->when($canViewPII, $profile->gender),
                    'county_id' => $profile->county_id,
                    'county' => $this->when($profile->relationLoaded('county') && $profile->county, function() use ($profile) {
                        return [
                            'id' => $profile->county->id,
                            'name' => $profile->county->name,
                        ];
                    }),
                    'occupation' => $profile->occupation,
                    'bio' => $profile->bio,
                    'interests' => $profile->interests,
                    'emergency_contact_name' => $this->when($canViewPII, $profile->emergency_contact_name),
                    'emergency_contact_phone' => $this->when($canViewPII, $profile->emergency_contact_phone),
                    'is_staff' => (bool) $profile->is_staff,
                    
                    // Public settings
                    'public_profile_enabled' => (bool) $profile->public_profile_enabled,
                    'public_bio' => $profile->public_bio,
                    'show_email' => (bool) $profile->show_email,
                ];
            }),

            // Activity data loaded ONLY for regular members (non-staff)
            'subscriptions' => $this->when(!$isStaff && $this->relationLoaded('subscriptions'), function() {
                return $this->subscriptions;
            }),
            'donations' => $this->when(!$isStaff && $this->relationLoaded('donations'), function() {
                return $this->donations;
            }),
            'event_orders' => $this->when(!$isStaff && $this->relationLoaded('eventOrders'), function() {
                return $this->eventOrders->map(fn($order) => [
                    'id' => $order->id,
                    'event' => $order->event ? ['id' => $order->event->id, 'title' => $order->event->title] : null,
                    'quantity' => $order->quantity,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                ]);
            }),

            // Computed capabilities for UI
            'ui_permissions' => [
                'can_access_admin' => $isStaff,
            ],
        ];
    }
}
