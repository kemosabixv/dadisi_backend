<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

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
     */
    public function create(User $user): bool
    {
        // Handled by EventQuotaService in Controller/Service
        return true;
    }

    /**
     * Determine whether the user can update the model.
     * 
     * - Organizers can always update their own events
     * - Admins can update any event
     * - Staff with edit_events permission can update organization events (for collaboration)
     */
    public function update(User $user, Event $event): bool
    {
        // Organizer can always edit their own events
        if ($user->id === $event->organizer_id) {
            return true;
        }
        
        // Admin panel access grants full control
        if ($user->canAccessAdminPanel()) {
            return true;
        }
        
        // Staff with edit_events permission can edit organization events
        if ($event->event_type === 'organization' && $user->hasPermissionTo('edit_events')) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * 
     * - Organizers can delete their own events
     * - Admins can delete any event
     * - Staff with delete_events permission can delete organization events
     */
    public function delete(User $user, Event $event): bool
    {
        // Organizer can always delete their own events
        if ($user->id === $event->organizer_id) {
            return true;
        }
        
        // Admin panel access grants full control
        if ($user->canAccessAdminPanel()) {
            return true;
        }
        
        // Staff with delete_events permission can delete organization events
        if ($event->event_type === 'organization' && $user->hasPermissionTo('delete_events')) {
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
