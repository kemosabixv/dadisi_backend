<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Removes the legacy 'approve_lab_bookings' permission that is no longer used.
     * This permission was previously assigned to admin and lab_manager roles but
     * all booking approval logic now uses 'view_all_lab_bookings' + 'mark_lab_attendance' instead.
     */
    public function up(): void
    {
        // Remove permission from role_has_permissions table
        DB::table('role_has_permissions')
            ->whereIn('permission_id', function ($query) {
                $query->select('id')
                    ->from('permissions')
                    ->where('name', 'approve_lab_bookings')
                    ->where('guard_name', 'api');
            })
            ->delete();

        // Remove permission from permissions table
        DB::table('permissions')
            ->where('name', 'approve_lab_bookings')
            ->where('guard_name', 'api')
            ->delete();

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     *
     * Restores the 'approve_lab_bookings' permission and reassigns it to
     * admin and lab_manager roles for rollback purposes.
     */
    public function down(): void
    {
        // Recreate the permission
        $permission = Permission::create([
            'name' => 'approve_lab_bookings',
            'guard_name' => 'api',
        ]);

        // Restore to roles that previously had it
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        $labManagerRole = Role::where('name', 'lab_manager')->where('guard_name', 'api')->first();

        if ($adminRole) {
            $adminRole->givePermissionTo($permission);
        }

        if ($labManagerRole) {
            $labManagerRole->givePermissionTo($permission);
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
