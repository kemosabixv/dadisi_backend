<?php

namespace Tests\Unit\Services\Users;

use App\Exceptions\UserException;
use App\Models\User;
use App\Services\Users\UserBulkOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * UserBulkOperationService Unit Tests
 *
 * Tests bulk operations including role assignment, role removal,
 * user deletion, user restoration, and user data updates with
 * transaction support and operation limits.
 *
 * Test Coverage:
 * - Bulk role assignment (100 user max)
 * - Bulk role removal (100 user max)
 * - Bulk user deletion (50 user max)
 * - Bulk user restoration (50 user max)
 * - Bulk user updates (50 user max)
 * - Duplicate prevention
 * - Operation limit enforcement
 * - Transaction support
 * - Error handling and exceptions
 */
class UserBulkOperationServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private UserBulkOperationService $bulkService;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure roles and permissions are seeded
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        
        $this->bulkService = app(UserBulkOperationService::class);
        
        // Create an authenticated user (actor) performing the bulk operations
        $this->actor = User::factory()->create();
        $this->actor->assignRole('super_admin');

        // Create additional test roles if they don't exist
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'moderator']);
        Role::firstOrCreate(['name' => 'member']);
    }

    // ============================================================
    // BULK ROLE ASSIGNMENT TESTS
    // ============================================================

    #[Test]
    public function it_can_assign_role_to_multiple_users(): void
    {
        // Arrange
        $users = User::factory()->count(5)->create();
        $userIds = $users->pluck('id')->toArray();

        // Act
        $count = $this->bulkService->bulkAssignRole($this->actor, $userIds, 'admin');

        // Assert
        $this->assertEquals(5, $count);
        foreach ($users as $user) {
            $this->assertTrue($user->fresh()->hasRole('admin'));
        }
    }

    #[Test]
    public function it_prevents_duplicate_role_assignment_in_bulk(): void
    {
        // Arrange
        $users = User::factory()->count(3)->create();
        $users->each(fn ($user) => $user->assignRole('admin'));
        $userIds = $users->pluck('id')->toArray();

        // Act
        $count = $this->bulkService->bulkAssignRole($this->actor, $userIds, 'admin');

        // Assert
        $this->assertEquals(0, $count);
        foreach ($users as $user) {
            // Should still have only one admin role
            $adminCount = $user->fresh()->roles()->where('name', 'admin')->count();
            $this->assertEquals(1, $adminCount);
        }
    }

    #[Test]
    public function it_respects_bulk_role_assignment_limit(): void
    {
        // Arrange
        $users = User::factory()->count(105)->create();
        $userIds = $users->pluck('id')->toArray();

        // Act & Assert
        $this->expectException(UserException::class);
        $this->bulkService->bulkAssignRole($this->actor, $userIds, 'admin');
    }

    #[Test]
    public function it_returns_correct_count_on_bulk_role_assignment(): void
    {
        // Arrange
        $users = User::factory()->count(25)->create();
        $userIds = $users->pluck('id')->toArray();

        // Act
        $count = $this->bulkService->bulkAssignRole($this->actor, $userIds, 'moderator');

        // Assert
        $this->assertEquals(25, $count);
        $this->assertEquals(25, User::role('moderator')->count());
    }

    #[Test]
    public function it_throws_exception_on_invalid_role_bulk_assignment(): void
    {
        // Arrange
        $users = User::factory()->count(5)->create();
        $userIds = $users->pluck('id')->toArray();

        // Act & Assert
        $this->expectException(UserException::class);
        $this->bulkService->bulkAssignRole($this->actor, $userIds, 'nonexistent_role');
    }

    // ============================================================
    // BULK ROLE REMOVAL TESTS
    // ============================================================

    #[Test]
    public function it_can_remove_role_from_multiple_users(): void
    {
        // Arrange
        $users = User::factory()->count(5)->create();
        $users->each(fn ($user) => $user->assignRole('admin'));
        $userIds = $users->pluck('id')->toArray();

        // Act
        $count = $this->bulkService->bulkRemoveRole($this->actor, $userIds, 'admin');

        // Assert
        $this->assertEquals(5, $count);
        foreach ($users as $user) {
            $this->assertFalse($user->fresh()->hasRole('admin'));
        }
    }

    #[Test]
    public function it_only_removes_role_from_users_who_have_it(): void
    {
        // Arrange
        $usersWithRole = User::factory()->count(3)->create();
        $usersWithRole->each(fn ($user) => $user->assignRole('admin'));
        $usersWithoutRole = User::factory()->count(2)->create();

        $allUserIds = array_merge(
            $usersWithRole->pluck('id')->toArray(),
            $usersWithoutRole->pluck('id')->toArray()
        );

        // Act
        $count = $this->bulkService->bulkRemoveRole($this->actor, $allUserIds, 'admin');

        // Assert
        $this->assertEquals(3, $count);
        $usersWithRole->each(
            fn ($user) => $this->assertFalse($user->fresh()->hasRole('admin'))
        );
    }

    #[Test]
    public function it_respects_bulk_role_removal_limit(): void
    {
        // Arrange
        $users = User::factory()->count(105)->create();
        $users->each(fn ($user) => $user->assignRole('admin'));
        $userIds = $users->pluck('id')->toArray();

        // Act & Assert
        $this->expectException(UserException::class);
        $this->bulkService->bulkRemoveRole($this->actor, $userIds, 'admin');
    }

    // ============================================================
    // BULK USER DELETION TESTS
    // ============================================================

    #[Test]
    public function it_can_soft_delete_multiple_users(): void
    {
        // Arrange
        $users = User::factory()->count(10)->create();
        $userIds = $users->pluck('id')->toArray();

        // Act
        $count = $this->bulkService->bulkDelete($this->actor, $userIds);

        // Assert
        $this->assertEquals(10, $count);
        foreach ($users as $user) {
            $this->assertSoftDeleted('users', ['id' => $user->id]);
        }
    }

    #[Test]
    public function it_respects_bulk_deletion_limit(): void
    {
        // Arrange
        $users = User::factory()->count(55)->create();
        $userIds = $users->pluck('id')->toArray();

        // Act & Assert
        $this->expectException(UserException::class);
        $this->bulkService->bulkDelete($this->actor, $userIds);
    }

    #[Test]
    public function it_returns_correct_count_on_bulk_deletion(): void
    {
        // Arrange
        $users = User::factory()->count(20)->create();
        $userIds = $users->pluck('id')->toArray();

        // Act
        $count = $this->bulkService->bulkDelete($this->actor, $userIds);

        // Assert
        $this->assertEquals(20, $count);
    }

    #[Test]
    public function it_returns_zero_when_deleting_nonexistent_users(): void
    {
        // Arrange
        $userIds = [99999, 99998, 99997];

        // Act
        $count = $this->bulkService->bulkDelete($this->actor, $userIds);

        // Assert
        $this->assertEquals(0, $count);
    }

    // ============================================================
    // BULK USER RESTORATION TESTS
    // ============================================================

    #[Test]
    public function it_can_restore_multiple_deleted_users(): void
    {
        // Arrange
        $users = User::factory()->count(10)->create();
        $users->each(fn ($user) => $user->delete());
        $userIds = $users->pluck('id')->toArray();

        // Act
        $count = $this->bulkService->bulkRestore($this->actor, $userIds);

        // Assert
        $this->assertEquals(10, $count);
        foreach ($users as $user) {
            $this->assertNotSoftDeleted('users', ['id' => $user->id]);
        }
    }

    #[Test]
    public function it_respects_bulk_restoration_limit(): void
    {
        // Arrange
        $users = User::factory()->count(55)->create();
        $users->each(fn ($user) => $user->delete());
        $userIds = $users->pluck('id')->toArray();

        // Act & Assert
        $this->expectException(UserException::class);
        $this->bulkService->bulkRestore($this->actor, $userIds);
    }

    #[Test]
    public function it_only_restores_deleted_users(): void
    {
        // Arrange
        $deletedUsers = User::factory()->count(3)->create();
        $deletedUsers->each(fn ($user) => $user->delete());
        $liveUsers = User::factory()->count(2)->create();

        $allUserIds = array_merge(
            $deletedUsers->pluck('id')->toArray(),
            $liveUsers->pluck('id')->toArray()
        );

        // Act
        $count = $this->bulkService->bulkRestore($this->actor, $allUserIds);

        // Assert
        $this->assertEquals(3, $count);
        $deletedUsers->each(
            fn ($user) => $this->assertNotSoftDeleted('users', ['id' => $user->id])
        );
    }

    #[Test]
    public function it_returns_zero_when_restoring_nonexistent_users(): void
    {
        // Arrange
        $userIds = [99999, 99998, 99997];

        // Act
        $count = $this->bulkService->bulkRestore($this->actor, $userIds);

        // Assert
        $this->assertEquals(0, $count);
    }

    // ============================================================
    // BULK USER UPDATE TESTS
    // ============================================================

    #[Test]
    public function it_can_update_multiple_users_with_data(): void
    {
        // Arrange
        $users = User::factory()->count(5)->create();
        $userIds = $users->pluck('id')->toArray();
        $updateData = ['profile_picture_path' => '/images/avatar.jpg'];

        // Act
        $count = $this->bulkService->bulkUpdate($this->actor, $userIds, $updateData);

        // Assert
        $this->assertEquals(5, $count);
        foreach ($users as $user) {
            $this->assertEquals('/images/avatar.jpg', $user->fresh()->profile_picture_path);
        }
    }

    #[Test]
    public function it_can_update_specific_columns(): void
    {
        // Arrange
        $users = User::factory()->count(3)->create();
        $userIds = $users->pluck('id')->toArray();
        $updateData = ['profile_picture_path' => '/images/default.png'];

        // Act
        $count = $this->bulkService->bulkUpdate($this->actor, $userIds, $updateData);

        // Assert
        $this->assertEquals(3, $count);
        foreach ($users as $user) {
            $this->assertEquals('/images/default.png', $user->fresh()->profile_picture_path);
        }
    }

    #[Test]
    public function it_respects_bulk_update_limit(): void
    {
        // Arrange
        $users = User::factory()->count(55)->create();
        $userIds = $users->pluck('id')->toArray();
        $updateData = ['is_active' => false];

        // Act & Assert
        $this->expectException(UserException::class);
        $this->bulkService->bulkUpdate($this->actor, $userIds, $updateData);
    }

    #[Test]
    public function it_returns_correct_count_on_bulk_update(): void
    {
        // Arrange
        $users = User::factory()->count(15)->create();
        $userIds = $users->pluck('id')->toArray();
        $updateData = ['profile_picture_path' => '/images/profile.jpg'];

        // Act
        $count = $this->bulkService->bulkUpdate($this->actor, $userIds, $updateData);

        // Assert
        $this->assertEquals(15, $count);
    }

    #[Test]
    public function it_returns_zero_when_updating_nonexistent_users(): void
    {
        // Arrange
        $userIds = [99999, 99998, 99997];
        $updateData = ['profile_picture_path' => '/images/profile.jpg'];

        // Act
        $count = $this->bulkService->bulkUpdate($this->actor, $userIds, $updateData);

        // Assert
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_can_update_multiple_columns_simultaneously(): void
    {
        // Arrange
        $users = User::factory()->count(5)->create();
        $userIds = $users->pluck('id')->toArray();
        $updateData = [
            'profile_picture_path' => '/images/avatar.jpg',
            'email_verified_at' => now(),
        ];

        // Act
        $count = $this->bulkService->bulkUpdate($this->actor, $userIds, $updateData);

        // Assert
        $this->assertEquals(5, $count);
        foreach ($users as $user) {
            $fresh = $user->fresh();
            $this->assertEquals('/images/avatar.jpg', $fresh->profile_picture_path);
            $this->assertNotNull($fresh->email_verified_at);
        }
    }
}
