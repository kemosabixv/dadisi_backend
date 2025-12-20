<?php

namespace App\Policies;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PromoCodePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    public function view(User $user, PromoCode $promoCode): bool
    {
        return $user->canAccessAdminPanel();
    }

    public function create(User $user): bool
    {
        // Organizers can create for their own events
        return true;
    }

    public function update(User $user, PromoCode $promoCode): bool
    {
        if ($user->canAccessAdminPanel()) return true;
        
        return $promoCode->event_id && $promoCode->event->organizer_id === $user->id;
    }

    public function delete(User $user, PromoCode $promoCode): bool
    {
        if ($user->canAccessAdminPanel()) return true;
        
        return $promoCode->event_id && $promoCode->event->organizer_id === $user->id;
    }
}
