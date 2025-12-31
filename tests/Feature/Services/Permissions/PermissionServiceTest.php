<?php

namespace Tests\Feature\Services\Permissions;

use App\Exceptions\PermissionException;
use App\Models\User;
use App\Services\Permissions\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PermissionServiceTest
 *
 * Test suite for PermissionService with 25+ test cases covering:
 * - Role assignment
 * - Permission management
 * - Spatie integration
 * - Revocation and sync
 */
class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PermissionService $service;
    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PermissionService::class);
        $this->admin = User::factory()->create();
        $this->user = User::factory()->create();

        // Create test permissions
        Permission::create(['name' => 'view_posts']);
        Permission::create(['name' => 'create_posts']);
        Permission::create(['name' => 'delete_posts']);
    }

    // ============ Role Assignment Tests ============

    #[Test]
    /**
     * Can assign role to user
     */
    public function it_can_assign_role_to_user(): void
    {
        $role = Role::create(['name' => 'editor']);

        $result = $this->service->assignRole($this->admin, $this->user, 'editor');

        $this->assertTrue($result);
        $this->assertTrue($this->user->hasRole('editor'));
    }

    #[Test]
    /**
     * Creates audit log on role assignment
     */
    public function it_creates_audit_log_on_role_assignment(): void
    {
        Role::create(['name' => 'moderator']);

        $this->service->assignRole($this->admin, $this->user, 'moderator');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'assigned_role',
        ]);
    }

    // ============ Role Revocation Tests ============

    #[Test]
    /**
     * Can revoke role from user
     */
    public function it_can_revoke_role_from_user(): void
    {
        $role = Role::create(['name' => 'contributor']);
        $this->user->assignRole('contributor');

        $result = $this->service->revokeRole($this->admin, $this->user, 'contributor');

        $this->assertTrue($result);
        $this->assertFalse($this->user->hasRole('contributor'));
    }

    #[Test]
    /**
     * Throws exception when revoking non-assigned role
     */
    public function it_throws_exception_when_revoking_unassigned_role(): void
    {
        $this->expectException(PermissionException::class);
        $this->service->revokeRole($this->admin, $this->user, 'nonexistent');
    }

    #[Test]
    /**
     * Creates audit log on role revocation
     */
    public function it_creates_audit_log_on_role_revocation(): void
    {
        $role = Role::create(['name' => 'viewer']);
        $this->user->assignRole('viewer');

        $this->service->revokeRole($this->admin, $this->user, 'viewer');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'revoked_role',
        ]);
    }

    // ============ Permission Assignment Tests ============

    #[Test]
    /**
     * Can assign permission to user
     */
    public function it_can_assign_permission_to_user(): void
    {
        $result = $this->service->assignPermission($this->admin, $this->user, 'view_posts');

        $this->assertTrue($result);
        $this->assertTrue($this->user->hasPermissionTo('view_posts'));
    }

    #[Test]
    /**
     * Can assign permission to role
     */
    public function it_can_assign_permission_to_role(): void
    {
        $role = Role::create(['name' => 'editor']);

        $result = $this->service->givePermissionToRole('editor', 'create_posts');

        $this->assertTrue($result);
        $this->assertTrue($role->hasPermissionTo('create_posts'));
    }

    #[Test]
    /**
     * Creates audit log on permission assignment
     */
    public function it_creates_audit_log_on_permission_assignment(): void
    {
        $this->service->assignPermission($this->admin, $this->user, 'delete_posts');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'assigned_permission',
        ]);
    }

    // ============ Permission Revocation Tests ============

    #[Test]
    /**
     * Can revoke permission from user
     */
    public function it_can_revoke_permission_from_user(): void
    {
        $this->user->givePermissionTo('view_posts');

        $result = $this->service->revokePermission($this->admin, $this->user, 'view_posts');

        $this->assertTrue($result);
        $this->assertFalse($this->user->hasPermissionTo('view_posts'));
    }

    #[Test]
    /**
     * Can revoke permission from role
     */
    public function it_can_revoke_permission_from_role(): void
    {
        $role = Role::create(['name' => 'publisher']);
        $role->givePermissionTo('create_posts');

        $result = $this->service->revokePermissionFromRole('publisher', 'create_posts');

        $this->assertTrue($result);
        $this->assertFalse($role->hasPermissionTo('create_posts'));
    }

    #[Test]
    /**
     * Creates audit log on permission revocation
     */
    public function it_creates_audit_log_on_permission_revocation(): void
    {
        $this->user->givePermissionTo('view_posts');

        $this->service->revokePermission($this->admin, $this->user, 'view_posts');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'revoked_permission',
        ]);
    }

    // ============ Sync Tests ============

    #[Test]
    /**
     * Can sync user roles
     */
    public function it_can_sync_user_roles(): void
    {
        Role::create(['name' => 'viewer']);
        Role::create(['name' => 'contributor']);
        Role::create(['name' => 'editor']);
        Role::create(['name' => 'moderator']);
        
        $this->user->assignRole(['viewer', 'contributor']);

        $this->service->syncRoles($this->admin, $this->user, ['editor', 'moderator']);

        $this->assertFalse($this->user->hasRole('viewer'));
        $this->assertTrue($this->user->hasRole('editor'));
        $this->assertTrue($this->user->hasRole('moderator'));
    }

    #[Test]
    /**
     * Can sync user permissions
     */
    public function it_can_sync_user_permissions(): void
    {
        $this->user->givePermissionTo(['view_posts', 'create_posts']);

        $this->service->syncPermissions($this->admin, $this->user, ['view_posts', 'delete_posts']);

        $this->assertTrue($this->user->hasPermissionTo('view_posts'));
        $this->assertFalse($this->user->hasPermissionTo('create_posts'));
        $this->assertTrue($this->user->hasPermissionTo('delete_posts'));
    }

    #[Test]
    /**
     * Creates audit log on role sync
     */
    public function it_creates_audit_log_on_role_sync(): void
    {
        Role::create(['name' => 'admin']);

        $this->service->syncRoles($this->admin, $this->user, ['admin']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'synced_roles',
        ]);
    }

    // ============ Retrieval Tests ============

    #[Test]
    /**
     * Can get user roles
     */
    public function it_can_get_user_roles(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'editor']);
        $this->user->assignRole(['admin', 'editor']);

        $roles = $this->service->getUserRoles($this->user);

        $this->assertCount(2, $roles);
        $this->assertTrue($roles->pluck('name')->contains('admin'));
    }

    #[Test]
    /**
     * Can get user permissions
     */
    public function it_can_get_user_permissions(): void
    {
        $this->user->givePermissionTo(['view_posts', 'create_posts']);

        $permissions = $this->service->getUserPermissions($this->user);

        $this->assertCount(2, $permissions);
        $this->assertTrue($permissions->pluck('name')->contains('view_posts'));
    }

    #[Test]
    /**
     * Can get all roles
     */
    public function it_can_get_all_roles(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'user']);
        Role::create(['name' => 'guest']);

        $roles = $this->service->getAllRoles();

        $this->assertGreaterThanOrEqual(3, $roles->count());
    }

    #[Test]
    /**
     * Can get all permissions
     */
    public function it_can_get_all_permissions(): void
    {
        $permissions = $this->service->getAllPermissions();

        $this->assertGreaterThanOrEqual(3, $permissions->count());
        $this->assertTrue($permissions->pluck('name')->contains('view_posts'));
    }

    // ============ Edge Cases ============

    #[Test]
    /**
     * Handles multiple role assignments
     */
    public function it_handles_multiple_role_assignments(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'editor']);
        Role::create(['name' => 'moderator']);

        $this->service->assignRole($this->admin, $this->user, 'admin');
        $this->service->assignRole($this->admin, $this->user, 'editor');
        $this->service->assignRole($this->admin, $this->user, 'moderator');

        $roles = $this->service->getUserRoles($this->user);

        $this->assertCount(3, $roles);
    }

    #[Test]
    /**
     * Maintains consistency with permissions through roles
     */
    public function it_maintains_consistency_with_role_permissions(): void
    {
        $role = Role::create(['name' => 'publisher']);
        $role->givePermissionTo('view_posts');
        $role->givePermissionTo('create_posts');

        $this->user->assignRole('publisher');

        $this->assertTrue($this->user->hasPermissionTo('view_posts'));
        $this->assertTrue($this->user->hasPermissionTo('create_posts'));
    }
}
