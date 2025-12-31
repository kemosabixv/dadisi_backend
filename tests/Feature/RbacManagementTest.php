<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacManagementTest extends TestCase
{
    use RefreshDatabase;

    private $superAdmin;

    private $regularAdmin;

    private $regularUser;

    private $testPermission;

    private $testRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeder to create roles and permissions
        $this->seed(RolesPermissionsSeeder::class);

        // Create test users
        $this->superAdmin = User::factory()->create([
            'email' => 'super-admin@example.com',
        ]);
        $this->superAdmin->assignRole(Role::where('name', 'super_admin')->get());
        $this->superAdmin = $this->superAdmin->fresh();

        $this->regularAdmin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
        $this->regularAdmin->assignRole(Role::where('name', 'admin')->get());
        $this->regularAdmin = $this->regularAdmin->fresh();

        $this->regularUser = User::factory()->create([
            'email' => 'user@example.com',
        ]);
        $this->regularUser->assignRole(Role::where('name', 'member')->get());
        $this->regularUser = $this->regularUser->fresh();

        // Create test permission and role for testing
        $this->testPermission = Permission::create(['name' => 'test_permission']);
        $this->testRole = Role::create(['name' => 'test_role']);
    }

    // =====================================
    // PERMISSION ENDPOINTS TESTS
    // =====================================

    #[Test]
    public function super_admin_can_list_permissions()
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/admin/permissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'guard_name', 'created_at', 'updated_at'],
                    ],
                ],
            ])
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function non_super_admin_cannot_list_permissions()
    {
        // Admin user
        $response = $this->actingAs($this->regularAdmin, 'sanctum')
            ->getJson('/api/admin/permissions');
        $response->assertStatus(403);

        // Regular user
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/admin/permissions');
        $response->assertStatus(403);

        // Unauthenticated
        $response = $this->getJson('/api/admin/permissions');
        $response->assertStatus(403);
    }



    #[Test]
    public function super_admin_can_view_single_permission()
    {
        $permission = Permission::where('name', 'test_permission')->first();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/admin/permissions/{$permission->name}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'name', 'guard_name', 'created_at', 'updated_at', 'roles',
                ],
            ]);
    }

    // =====================================
    // ROLE ENDPOINTS TESTS
    // =====================================

    #[Test]
    public function super_admin_can_list_roles()
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/admin/roles?include_permissions=true');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'guard_name', 'created_at', 'updated_at', 'permissions'],
                    ],
                ],
            ]);
    }

    #[Test]
    public function non_super_admin_cannot_list_roles()
    {
        Sanctum::actingAs($this->regularAdmin);
        $response = $this->getJson('/api/admin/roles');
        $response->assertStatus(403);
    }

    #[Test]
    public function super_admin_can_create_role()
    {
        $payload = ['name' => 'new_test_role'];

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/admin/roles', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Role created successfully',
            ]);

        $this->assertDatabaseHas('roles', ['name' => 'new_test_role']);
    }

    #[Test]
    public function super_admin_can_update_role()
    {
        $role = Role::where('name', 'test_role')->first();

        $payload = ['name' => 'updated_test_role'];

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/admin/roles/{$role->name}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Role updated successfully',
            ]);

        $this->assertDatabaseHas('roles', ['name' => 'updated_test_role']);
    }

    #[Test]
    public function super_admin_can_view_single_role()
    {
        $role = Role::where('name', 'test_role')->first();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/admin/roles/{$role->name}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'name', 'guard_name', 'created_at', 'updated_at', 'permissions', 'users_count',
                ],
            ]);
    }

    #[Test]
    public function cannot_delete_role_with_assigned_users()
    {
        $role = Role::where('name', 'member')->where('guard_name', 'api')->first(); // This has users

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete role that has assigned users',
            ]);

        $this->assertDatabaseHas('roles', ['name' => 'member']);
    }

    // =====================================
    // PERMISSION ASSIGNMENT TESTS
    // =====================================

    #[Test]
    public function super_admin_can_assign_permissions_to_role()
    {
        $role = Role::where('name', 'test_role')->first();

        $payload = [
            'permissions' => ['manage_users', 'view_all_users'],
        ];

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/admin/roles/{$role->name}/permissions", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Permissions assigned to role successfully',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'role',
                    'assigned_permissions',
                    'current_permissions',
                ],
            ]);

        // Verify permissions were assigned
        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('manage_users'));
        $this->assertTrue($role->hasPermissionTo('view_all_users'));
    }

    #[Test]
    public function super_admin_can_remove_permissions_from_role()
    {
        // First assign some permissions
        $role = Role::where('name', 'test_role')->first();
        $role->givePermissionTo(['manage_users', 'view_all_users']);

        // Then remove them
        $payload = [
            'permissions' => ['manage_users'],
        ];

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/admin/roles/{$role->name}/permissions", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Permissions removed from role successfully',
            ]);

        // Verify permission was removed but one remains
        $role->refresh();
        $this->assertFalse($role->hasPermissionTo('manage_users'));
        $this->assertTrue($role->hasPermissionTo('view_all_users'));
    }

    #[Test]
    public function permission_assignment_validation_works()
    {
        $role = Role::where('name', 'test_role')->first();
        // Empty permissions array
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/admin/roles/{$role->name}/permissions", ['permissions' => []]);
        $response->assertStatus(422);

        // Invalid permission name
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/admin/roles/{$role->name}/permissions", ['permissions' => ['nonexistent_permission']]);
        $response->assertStatus(422);
    }

    #[Test]
    public function non_super_admin_cannot_manage_permissions_or_roles()
    {
        // Try to assign permissions to role
        $role = Role::where('name', 'test_role')->first();
        $response = $this->actingAs($this->regularAdmin, 'sanctum')
            ->postJson("/api/admin/roles/{$role->name}/permissions", [
                'permissions' => ['manage_users'],
            ]);
        $response->assertStatus(403);

        // Try to create role
        $response = $this->actingAs($this->regularAdmin, 'sanctum')
            ->postJson('/api/admin/roles', ['name' => 'test_role']);
        $response->assertStatus(403);
    }

    #[Test]
    public function search_functionality_works()
    {
        // Test permission search
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/admin/permissions?search=manage');

        $response->assertStatus(200);
        $permissions = $response->json('data.data');

        // Should find permissions containing "manage"
        foreach ($permissions as $permission) {
            $this->assertStringContainsString('manage', $permission['name']);
        }

        // Test role search
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/admin/roles?search=admin');

        $response->assertStatus(200);
        $roles = $response->json('data.data');

        // Should find roles containing "admin"
        foreach ($roles as $role) {
            $this->assertStringContainsString('admin', $role['name']);
        }
    }
}
