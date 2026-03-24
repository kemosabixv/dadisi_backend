<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PublicProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'profile_picture_url' => $this->profile_picture_url,
            'joined_at' => $this->joined_at,
            'full_name' => $this->full_name,
            'age' => $this->age,
            'bio' => $this->bio,
            'public_role' => $this->public_role,
            'location' => $this->location,
            'interests' => $this->interests,
            'occupation' => $this->occupation,
            'email' => $this->email,
            'thread_count' => $this->when($this->show_post_count, $this->thread_count),
            'post_count' => $this->when($this->show_post_count, $this->post_count),
            'is_staff' => $this->is_staff,
            'sections' => $this->sections,
        ];
    }
}
