<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Event Policy
 * 
 * All events are now staff-created only (organization events).
 * Regular users can only view events and RSVP/register.
 */
class EventPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Event $event): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     * Only admin/staff can create events.
     */
    public function create(User $user): bool
    {
        return $user->canAccessAdminPanel() || $user->hasPermissionTo('create_events');
    }

    /**
     * Determine whether the user can update the model.
     * Only admin/staff can update events.
     */
    public function update(User $user, Event $event): bool
    {
        if ($user->canAccessAdminPanel()) {
            return true;
        }
        
        if ($user->hasPermissionTo('edit_events')) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * Only admin/staff can delete events.
     */
    public function delete(User $user, Event $event): bool
    {
        if ($user->canAccessAdminPanel()) {
            return true;
        }
        
        if ($user->hasPermissionTo('delete_events')) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Event $event): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return $user->canAccessAdminPanel();
    }
}
