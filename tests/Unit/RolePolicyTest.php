<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Policies\RolePolicy;
use Spatie\Permission\Models\Role;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;


class RolePolicyTest extends TestCase
{
    use RefreshDatabase;

    private $policy;
    private $superAdmin;
    private $regularAdmin;
    private $regularUser;
    private $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new RolePolicy();

        // Run seeder to create roles and permissions
        $this->seed(RolesPermissionsSeeder::class);

        // Create test users
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');
        $this->superAdmin = $this->superAdmin->fresh(); // Reload from database

        $this->regularAdmin = User::factory()->create();
        $this->regularAdmin->assignRole('admin');
        $this->regularAdmin = $this->regularAdmin->fresh(); // Reload from database

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('member');
        $this->regularUser = $this->regularUser->fresh(); // Reload from database

        // Create test role
        $this->role = Role::create(['name' => 'test_role']);
    }

    #[Test]
    public function super_admin_can_view_any_roles()
    {
        $this->assertTrue($this->policy->viewAny($this->superAdmin));
    }

    #[Test]
    public function non_super_admin_cannot_view_any_roles()
    {
        $this->assertFalse($this->policy->viewAny($this->regularAdmin));
        $this->assertFalse($this->policy->viewAny($this->regularUser));
    }

    #[Test]
    public function super_admin_can_view_specific_role()
    {
        $this->assertTrue($this->policy->view($this->superAdmin, $this->role));
    }

    #[Test]
    public function non_super_admin_cannot_view_specific_role()
    {
        $this->assertFalse($this->policy->view($this->regularAdmin, $this->role));
        $this->assertFalse($this->policy->view($this->regularUser, $this->role));
    }

    #[Test]
    public function super_admin_can_create_roles()
    {
        $this->assertTrue($this->policy->create($this->superAdmin));
    }

    #[Test]
    public function non_super_admin_cannot_create_roles()
    {
        $this->assertFalse($this->policy->create($this->regularAdmin));
        $this->assertFalse($this->policy->create($this->regularUser));
    }

    #[Test]
    public function super_admin_can_update_roles()
    {
        $this->assertTrue($this->policy->update($this->superAdmin, $this->role));
    }

    #[Test]
    public function non_super_admin_cannot_update_roles()
    {
        $this->assertFalse($this->policy->update($this->regularAdmin, $this->role));
        $this->assertFalse($this->policy->update($this->regularUser, $this->role));
    }

    #[Test]
    public function super_admin_can_delete_roles()
    {
        $this->assertTrue($this->policy->delete($this->superAdmin, $this->role));
    }

    #[Test]
    public function non_super_admin_cannot_delete_roles()
    {
        $this->assertFalse($this->policy->delete($this->regularAdmin, $this->role));
        $this->assertFalse($this->policy->delete($this->regularUser, $this->role));
    }

    #[Test]
    public function super_admin_can_manage_permissions_in_roles()
    {
        $this->assertTrue($this->policy->managePermissions($this->superAdmin, $this->role));
    }

    #[Test]
    public function non_super_admin_cannot_manage_permissions_in_roles()
    {
        $this->assertFalse($this->policy->managePermissions($this->regularAdmin, $this->role));
        $this->assertFalse($this->policy->managePermissions($this->regularUser, $this->role));
    }

    #[Test]
    public function super_admin_can_restore_roles()
    {
        $this->assertTrue($this->policy->restore($this->superAdmin, $this->role));
    }

    #[Test]
    public function non_super_admin_cannot_restore_roles()
    {
        $this->assertFalse($this->policy->restore($this->regularAdmin, $this->role));
        $this->assertFalse($this->policy->restore($this->regularUser, $this->role));
    }

    #[Test]
    public function super_admin_can_force_delete_roles()
    {
        $this->assertTrue($this->policy->forceDelete($this->superAdmin, $this->role));
    }

    #[Test]
    public function non_super_admin_cannot_force_delete_roles()
    {
        $this->assertFalse($this->policy->forceDelete($this->regularAdmin, $this->role));
        $this->assertFalse($this->policy->forceDelete($this->regularUser, $this->role));
    }
}
