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
        $profile = $this->memberProfile;
        $isStaff = AdminAccessResolver::canAccessAdmin($this->resource);
        
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'profile_picture_url' => $this->profile_picture_url,

            // Roles for display
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ]);
            }, []),

            // Full profile data for admin user detail
            'member_profile' => $profile ? [
                'id' => $profile->id,
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
                'phone_number' => $profile->phone_number,
                'date_of_birth' => $profile->date_of_birth?->format('Y-m-d'),
                'gender' => $profile->gender,
                'county_id' => $profile->county_id,
                'county' => $profile->county ? [
                    'id' => $profile->county->id,
                    'name' => $profile->county->name,
                ] : null,
                'sub_county' => $profile->sub_county,
                'ward' => $profile->ward,
                'occupation' => $profile->occupation,
                'bio' => $profile->bio,
                'interests' => $profile->interests,
                'emergency_contact_name' => $profile->emergency_contact_name,
                'emergency_contact_phone' => $profile->emergency_contact_phone,
                'is_staff' => (bool) $profile->is_staff,
                'terms_accepted' => (bool) $profile->terms_accepted,
                'marketing_consent' => (bool) $profile->marketing_consent,
                
                // Privacy and display settings
                'public_profile_enabled' => (bool) $profile->public_profile_enabled,
                'public_bio' => $profile->public_bio,
                'show_email' => (bool) $profile->show_email,
                'show_location' => (bool) $profile->show_location,
                'show_join_date' => (bool) $profile->show_join_date,
                'show_post_count' => (bool) $profile->show_post_count,
                'show_interests' => (bool) $profile->show_interests,
                'show_occupation' => (bool) $profile->show_occupation,
            ] : null,

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
                    'currency' => $order->currency,
                    'status' => $order->status,
                    'reference' => $order->reference,
                    'checked_in_at' => $order->checked_in_at,
                    'created_at' => $order->created_at,
                ]);
            }),
            'lab_bookings' => $this->when(!$isStaff && $this->relationLoaded('labBookings'), function() {
                return $this->labBookings;
            }),
            'forum_threads' => $this->when(!$isStaff && $this->relationLoaded('forumThreads'), function() {
                return $this->forumThreads->map(fn($thread) => [
                    'id' => $thread->id,
                    'title' => $thread->title,
                    'created_at' => $thread->created_at,
                ]);
            }),
            'forum_posts' => $this->when(!$isStaff && $this->relationLoaded('forumPosts'), function() {
                return $this->forumPosts->map(fn($post) => [
                    'id' => $post->id,
                    'thread_title' => $post->thread?->title,
                    'created_at' => $post->created_at,
                ]);
            }),

            // Computed capabilities for UI
            'ui_permissions' => [
                'can_access_admin' => $isStaff,
            ],
        ];
    }
}
