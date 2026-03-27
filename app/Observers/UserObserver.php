<?php

namespace App\Observers;

use App\Models\User;
use Spatie\Permission\Models\Role;

class UserObserver
{
    /**
     * Handle the User "created" event.
     * Automatically assigns the 'member' role to every new user.
     */
    public function created(User $user): void
    {
        // Ensure the 'member' role exists before assigning
        // (Usually handled by seeders, but defensive check)
        if (Role::where('name', 'member')->exists()) {
            $user->assignRole('member');
        }
    }
}
