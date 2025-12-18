<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MemberProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone_number' => $this->phone_number,
            'date_of_birth' => $this->date_of_birth ? (string) $this->date_of_birth : null,
            'gender' => $this->gender,
            'county_id' => $this->county_id,
            'sub_county' => $this->sub_county,
            'ward' => $this->ward,
            'interests' => $this->interests,
            'bio' => $this->bio,
            'is_staff' => (bool) $this->is_staff,
            'plan_id' => $this->plan_id,
            'plan_type' => $this->plan_type,
            'plan_expires_at' => $this->plan_expires_at ? (string) $this->plan_expires_at : null,
            'occupation' => $this->occupation,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'terms_accepted' => (bool) $this->terms_accepted,
            'marketing_consent' => (bool) $this->marketing_consent,
            'created_at' => (string) $this->created_at,
            'updated_at' => (string) $this->updated_at,
            'deleted_at' => $this->deleted_at ? (string) $this->deleted_at : null,

            // Relations (only if loaded)
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id ?? null,
                    'name' => $this->user->name ?? null,
                    'username' => $this->user->username ?? null,
                    'email' => $this->user->email ?? null,
                    'phone' => $this->user->phone ?? null,
                    'email_verified_at' => isset($this->user->email_verified_at) ? (string) $this->user->email_verified_at : null,
                    'profile_picture_url' => $this->user->profile_picture_url ?? null,
                ];
            }),

            'county' => $this->whenLoaded('county', function () {
                return [
                    'id' => $this->county->id ?? null,
                    'name' => $this->county->name ?? null,
                ];
            }),

            'subscription_plan' => $this->whenLoaded('subscriptionPlan', function () {
                // subscriptionPlan may come from a different package; be defensive
                $plan = $this->subscriptionPlan;
                if (!$plan) {
                    return null;
                }
                return [
                    'id' => $plan->id ?? null,
                    'name' => $plan->name ?? null,
                    'slug' => $plan->slug ?? null,
                    'description' => $plan->description ?? null,
                    'price' => $plan->price ?? null,
                ];
            }),
        ];
    }
}
