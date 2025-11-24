<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Policies\PermissionPolicy;
use Spatie\Permission\Models\Permission;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;


class PermissionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private $policy;
    private $superAdmin;
    private $regularAdmin;
    private $regularUser;
    private $permission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new PermissionPolicy();

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

        // Create test permission
        $this->permission = Permission::create(['name' => 'test_permission']);
    }

    #[Test]
    public function super_admin_can_view_any_permissions()
    {
        $this->assertTrue($this->policy->viewAny($this->superAdmin));
    }

    #[Test]
    public function non_super_admin_cannot_view_any_permissions()
    {
        $this->assertFalse($this->policy->viewAny($this->regularAdmin));
        $this->assertFalse($this->policy->viewAny($this->regularUser));
    }

    #[Test]
    public function super_admin_can_view_specific_permission()
    {
        $this->assertTrue($this->policy->view($this->superAdmin, $this->permission));
    }

    #[Test]
    public function non_super_admin_cannot_view_specific_permission()
    {
        $this->assertFalse($this->policy->view($this->regularAdmin, $this->permission));
        $this->assertFalse($this->policy->view($this->regularUser, $this->permission));
    }

    #[Test]
    public function super_admin_can_create_permissions()
    {
        $this->assertTrue($this->policy->create($this->superAdmin));
    }

    #[Test]
    public function non_super_admin_cannot_create_permissions()
    {
        $this->assertFalse($this->policy->create($this->regularAdmin));
        $this->assertFalse($this->policy->create($this->regularUser));
    }

    #[Test]
    public function super_admin_can_update_permissions()
    {
        $this->assertTrue($this->policy->update($this->superAdmin, $this->permission));
    }

    #[Test]
    public function non_super_admin_cannot_update_permissions()
    {
        $this->assertFalse($this->policy->update($this->regularAdmin, $this->permission));
        $this->assertFalse($this->policy->update($this->regularUser, $this->permission));
    }

    #[Test]
    public function super_admin_can_delete_permissions()
    {
        $this->assertTrue($this->policy->delete($this->superAdmin, $this->permission));
    }

    #[Test]
    public function non_super_admin_cannot_delete_permissions()
    {
        $this->assertFalse($this->policy->delete($this->regularAdmin, $this->permission));
        $this->assertFalse($this->policy->delete($this->regularUser, $this->permission));
    }

    #[Test]
    public function super_admin_can_restore_permissions()
    {
        $this->assertTrue($this->policy->restore($this->superAdmin, $this->permission));
    }

    #[Test]
    public function non_super_admin_cannot_restore_permissions()
    {
        $this->assertFalse($this->policy->restore($this->regularAdmin, $this->permission));
        $this->assertFalse($this->policy->restore($this->regularUser, $this->permission));
    }

    #[Test]
    public function super_admin_can_force_delete_permissions()
    {
        $this->assertTrue($this->policy->forceDelete($this->superAdmin, $this->permission));
    }

    #[Test]
    public function non_super_admin_cannot_force_delete_permissions()
    {
        $this->assertFalse($this->policy->forceDelete($this->regularAdmin, $this->permission));
        $this->assertFalse($this->policy->forceDelete($this->regularUser, $this->permission));
    }
}
