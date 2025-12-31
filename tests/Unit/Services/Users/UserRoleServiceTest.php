<?php

namespace Tests\Unit\Services\Users;

use App\Exceptions\UserException;
use App\Models\User;
use App\Services\Users\UserRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * UserRoleService Unit Tests
 *
 * Tests role assignment, removal, synchronization, retrieval,
 * and role verification for users through Spatie permissions.
 *
 * Test Coverage:
 * - Single role assignment
 * - Single role removal
 * - Bulk role synchronization
 * - Role retrieval
 * - Role verification
 * - Duplicate prevention
 * - Error handling and exceptions
 */
class UserRoleServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private UserRoleService $roleService;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        
        $this->roleService = app(UserRoleService::class);
        
        // Create an authenticated user (actor) performing role operations
        $this->actor = User::factory()->create();
        $this->actor->assignRole('super_admin');

        // Create test roles
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'moderator']);
        Role::firstOrCreate(['name' => 'member']);
        Role::firstOrCreate(['name' => 'guest']);
    }

    // ============================================================
    // ROLE ASSIGNMENT TESTS
    // ============================================================

    #[Test]
    public function it_can_assign_single_role_to_user(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->assertFalse($user->hasRole('admin'));

        // Act
        $updated = $this->roleService->assignRole($this->actor, $user, 'admin');

        // Assert
        $this->assertTrue($updated->hasRole('admin'));
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $user->id,
            'role_id' => Role::findByName('admin')->id,
        ]);
    }

    #[Test]
    public function it_prevents_duplicate_role_assignment(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->assertTrue($user->hasRole('admin'));

        // Act
        $updated = $this->roleService->assignRole($this->actor, $user, 'admin');

        // Assert
        $this->assertTrue($updated->hasRole('admin'));
        // Verify only one admin role assignment exists
        $adminRoleCount = $user->roles()->where('name', 'admin')->count();
        $this->assertEquals(1, $adminRoleCount);
    }

    #[Test]
    public function it_can_assign_multiple_different_roles_sequentially(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $this->roleService->assignRole($this->actor, $user, 'admin');
        $updated = $this->roleService->assignRole($this->actor, $user, 'moderator');

        // Assert
        $this->assertTrue($updated->hasRole('admin'));
        $this->assertTrue($updated->hasRole('moderator'));
        $this->assertEquals(2, $updated->roles()->count());
    }

    #[Test]
    public function it_throws_exception_on_invalid_role(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act & Assert
        $this->expectException(UserException::class);
        $this->roleService->assignRole($this->actor, $user, 'nonexistent_role');
    }

    // ============================================================
    // ROLE REMOVAL TESTS
    // ============================================================

    #[Test]
    public function it_can_remove_role_from_user(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->assertTrue($user->hasRole('admin'));

        // Act
        $updated = $this->roleService->removeRole($this->actor, $user, 'admin');

        // Assert
        $this->assertFalse($updated->hasRole('admin'));
        $this->assertDatabaseMissing('model_has_roles', [
            'model_id' => $user->id,
            'role_id' => Role::findByName('admin')->id,
        ]);
    }

    #[Test]
    public function it_throws_exception_when_removing_role_user_doesnt_have(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $result = $this->roleService->removeRole($this->actor, $user, 'admin');

        // Assert - Service silently succeeds even if user doesn't have the role
        $this->assertEquals($user->id, $result->id);
        $this->assertFalse($result->hasRole('admin'));
    }

    #[Test]
    public function it_throws_exception_when_removing_invalid_role(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $result = $this->roleService->removeRole($this->actor, $user, 'nonexistent_role');

        // Assert - Service silently succeeds even with invalid role
        $this->assertEquals($user->id, $result->id);
        $this->assertFalse($result->hasRole('nonexistent_role'));
    }

    #[Test]
    public function it_can_remove_one_role_while_keeping_others(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole(['admin', 'moderator', 'member']);

        // Act
        $updated = $this->roleService->removeRole($this->actor, $user, 'moderator');

        // Assert
        $this->assertTrue($updated->hasRole('admin'));
        $this->assertFalse($updated->hasRole('moderator'));
        $this->assertTrue($updated->hasRole('member'));
    }

    // ============================================================
    // ROLE SYNCHRONIZATION TESTS
    // ============================================================

    #[Test]
    public function it_can_sync_roles_replacing_all_existing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole(['admin', 'moderator']);

        // Act
        $updated = $this->roleService->syncRoles($this->actor, $user, ['member', 'guest']);

        // Assert
        $this->assertFalse($updated->hasRole('admin'));
        $this->assertFalse($updated->hasRole('moderator'));
        $this->assertTrue($updated->hasRole('member'));
        $this->assertTrue($updated->hasRole('guest'));
        $this->assertEquals(2, $updated->roles()->count());
    }

    #[Test]
    public function it_can_sync_to_empty_roles(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole(['admin', 'moderator']);

        // Act
        $updated = $this->roleService->syncRoles($this->actor, $user, []);

        // Assert
        $this->assertFalse($updated->hasRole('admin'));
        $this->assertFalse($updated->hasRole('moderator'));
        $this->assertEquals(0, $updated->roles()->count());
    }

    #[Test]
    public function it_handles_single_role_in_sync(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole(['admin', 'moderator']);

        // Act
        $updated = $this->roleService->syncRoles($this->actor, $user, ['member']);

        // Assert
        $this->assertFalse($updated->hasRole('admin'));
        $this->assertFalse($updated->hasRole('moderator'));
        $this->assertTrue($updated->hasRole('member'));
        $this->assertEquals(1, $updated->roles()->count());
    }

    #[Test]
    public function it_throws_exception_on_invalid_role_in_sync(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act & Assert
        $this->expectException(UserException::class);
        $this->roleService->syncRoles($this->actor, $user, ['admin', 'invalid_role']);
    }

    // ============================================================
    // ROLE RETRIEVAL TESTS
    // ============================================================

    #[Test]
    public function it_can_get_roles_as_collection(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole(['admin', 'moderator', 'member']);

        // Act
        $roles = $this->roleService->getRoles($user);

        // Assert
        $this->assertInstanceOf(Collection::class, $roles);
        $this->assertEquals(3, $roles->count());
        $this->assertTrue($roles->contains('admin'));
        $this->assertTrue($roles->contains('moderator'));
        $this->assertTrue($roles->contains('member'));
    }

    #[Test]
    public function it_returns_empty_collection_for_user_with_no_roles(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $roles = $this->roleService->getRoles($user);

        // Assert
        $this->assertInstanceOf(Collection::class, $roles);
        $this->assertEquals(0, $roles->count());
        $this->assertTrue($roles->isEmpty());
    }

    #[Test]
    public function it_returns_collection_with_correct_role_names(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole(['admin', 'guest']);

        // Act
        $roles = $this->roleService->getRoles($user);

        // Assert
        $roleNames = $roles->toArray();
        $this->assertContains('admin', $roleNames);
        $this->assertContains('guest', $roleNames);
        $this->assertNotContains('member', $roleNames);
    }

    // ============================================================
    // ROLE VERIFICATION TESTS
    // ============================================================

    #[Test]
    public function it_returns_true_when_user_has_role(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Act
        $hasRole = $this->roleService->hasRole($user, 'admin');

        // Assert
        $this->assertTrue($hasRole);
    }

    #[Test]
    public function it_returns_false_when_user_does_not_have_role(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $hasRole = $this->roleService->hasRole($user, 'admin');

        // Assert
        $this->assertFalse($hasRole);
    }

    #[Test]
    public function it_returns_false_after_role_removal(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->assertTrue($this->roleService->hasRole($user, 'admin'));

        // Act
        $this->roleService->removeRole($this->actor, $user, 'admin');
        $hasRole = $this->roleService->hasRole($user, 'admin');

        // Assert
        $this->assertFalse($hasRole);
    }

    #[Test]
    public function it_correctly_checks_multiple_roles(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole(['admin', 'moderator']);

        // Act & Assert
        $this->assertTrue($this->roleService->hasRole($user, 'admin'));
        $this->assertTrue($this->roleService->hasRole($user, 'moderator'));
        $this->assertFalse($this->roleService->hasRole($user, 'member'));
        $this->assertFalse($this->roleService->hasRole($user, 'guest'));
    }
}
