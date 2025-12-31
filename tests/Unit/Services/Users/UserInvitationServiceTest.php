<?php

namespace Tests\Unit\Services\Users;

use App\Exceptions\UserException;
use App\Models\User;
use App\Services\Users\UserInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * UserInvitationService Unit Tests
 *
 * Tests user invitation workflows including single and bulk
 * invitations, email sending, token verification, and acceptance.
 *
 * Test Coverage:
 * - Single user invitation
 * - Bulk user invitations
 * - Temporary password generation
 * - Invitation token generation
 * - Token verification
 * - Invitation acceptance
 * - Role assignment during invitation
 * - Email notification (mocked)
 * - Error handling and exceptions
 */
class UserInvitationServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private UserInvitationService $invitationService;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        
        $this->invitationService = app(UserInvitationService::class);
        
        // Create an authenticated user (actor) performing the invitations
        $this->actor = User::factory()->create();
        $this->actor->assignRole('super_admin');

        // Create test roles
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'moderator']);
        Role::firstOrCreate(['name' => 'member']);
    }

    // ============================================================
    // SINGLE INVITATION TESTS
    // ============================================================

    #[Test]
    public function it_can_invite_single_user(): void
    {
        // Arrange
        $invitationData = [
            'email' => 'newuser@example.com',
            'roles' => ['member'],
        ];

        // Act
        $result = $this->invitationService->invite($this->actor, $invitationData);

        // Assert
        $this->assertArrayHasKey('invitation', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('email', $result);

        $invitation = $result['invitation'];
        $this->assertEquals('newuser@example.com', $invitation->email);
        $this->assertEquals(['member'], $invitation->roles);
        $this->assertNotNull($invitation->token);
        $this->assertNull($invitation->accepted_at);
        
        // Ensure no user was created yet
        $this->assertDatabaseMissing('users', ['email' => 'newuser@example.com']);
    }

    #[Test]
    public function it_generates_valid_invitation_token(): void
    {
        // Arrange
        $invitationData = [
            'email' => 'tokentest@example.com',
            'roles' => [],
        ];

        // Act
        $result = $this->invitationService->invite($this->actor, $invitationData);
        $token = $result['token'];

        // Assert
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
    }

    #[Test]
    public function it_throws_exception_on_duplicate_email_invitation(): void
    {
        // Arrange
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);
        $invitationData = [
            'email' => 'existing@example.com',
            'roles' => [],
        ];

        // Act & Assert
        $this->expectException(UserException::class);
        $this->invitationService->invite($this->actor, $invitationData);
    }

    // ============================================================
    // BULK INVITATION TESTS
    // ============================================================

    #[Test]
    public function it_can_invite_multiple_users(): void
    {
        // Arrange
        $invitationsData = [
            ['email' => 'bulk1@example.com', 'roles' => ['member']],
            ['email' => 'bulk2@example.com', 'roles' => ['member']],
            ['email' => 'bulk3@example.com', 'roles' => ['admin']],
        ];

        // Act
        $count = $this->invitationService->bulkInvite($this->actor, $invitationsData);

        // Assert
        $this->assertEquals(3, $count);
        $this->assertDatabaseHas('user_invitations', ['email' => 'bulk1@example.com']);
        $this->assertDatabaseHas('user_invitations', ['email' => 'bulk2@example.com']);
        $this->assertDatabaseHas('user_invitations', ['email' => 'bulk3@example.com']);
    }

    // ============================================================
    // TOKEN VERIFICATION TESTS
    // ============================================================

    #[Test]
    public function it_can_verify_valid_invitation_token(): void
    {
        // Arrange
        $invitationData = ['email' => 'verifytoken@example.com', 'roles' => []];
        $result = $this->invitationService->invite($this->actor, $invitationData);
        $token = $result['token'];

        // Act
        $invitation = $this->invitationService->verifyInvitationToken($token);

        // Assert
        $this->assertInstanceOf(\App\Models\Invitation::class, $invitation);
        $this->assertEquals('verifytoken@example.com', $invitation->email);
    }

    #[Test]
    public function it_throws_exception_on_invalid_token(): void
    {
        // Arrange
        $invalidToken = Str::random(64);

        // Act & Assert
        $this->expectException(UserException::class);
        $this->invitationService->verifyInvitationToken($invalidToken);
    }

    #[Test]
    public function it_throws_exception_on_expired_token(): void
    {
        // Arrange
        $invitationData = ['email' => 'expired@example.com', 'roles' => []];
        $result = $this->invitationService->invite($this->actor, $invitationData);
        $invitation = $result['invitation'];
        
        // Force expiration
        $invitation->update(['expires_at' => now()->subDay()]);

        // Act & Assert
        $this->expectException(UserException::class);
        $this->invitationService->verifyInvitationToken($invitation->token);
    }

    // ============================================================
    // INVITATION ACCEPTANCE TESTS
    // ============================================================

    #[Test]
    public function it_can_accept_invitation_and_create_user(): void
    {
        // Arrange
        $invitationData = ['email' => 'accept@example.com', 'roles' => ['member']];
        $inviteResult = $this->invitationService->invite($this->actor, $invitationData);
        $token = $inviteResult['token'];

        $userData = [
            'username' => 'newuser',
            'password' => 'SecurePassword123!',
        ];

        // Act
        $user = $this->invitationService->acceptInvitation($token, $userData);

        // Assert
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('accept@example.com', $user->email);
        $this->assertEquals('newuser', $user->username);
        $this->assertTrue($user->hasRole('member'));
        $this->assertNotNull($user->email_verified_at);

        // Invitation should be marked as accepted
        $invitation = \App\Models\Invitation::where('email', 'accept@example.com')->first();
        $this->assertTrue($invitation->isAccepted());
    }

    #[Test]
    public function it_throws_exception_when_accepting_already_accepted_invitation(): void
    {
        // Arrange
        $invitationData = ['email' => 'already@example.com', 'roles' => []];
        $inviteResult = $this->invitationService->invite($this->actor, $invitationData);
        $token = $inviteResult['token'];

        $userData = ['username' => 'user1', 'password' => 'Pass123!'];
        $this->invitationService->acceptInvitation($token, $userData);

        // Act & Assert
        $this->expectException(UserException::class);
        $this->invitationService->acceptInvitation($token, $userData);
    }

    // ============================================================
    // COMPREHENSIVE WORKFLOW TESTS
    // ============================================================

    #[Test]
    public function it_supports_complete_invitation_workflow(): void
    {
        // Arrange
        $invitationData = [
            'email' => 'workflow@example.com',
            'roles' => ['member'],
        ];

        // Step 1: Invite user
        $result = $this->invitationService->invite($this->actor, $invitationData);
        $token = $result['token'];

        $this->assertDatabaseHas('user_invitations', ['email' => 'workflow@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'workflow@example.com']);

        // Step 2: Verify token
        $invitation = $this->invitationService->verifyInvitationToken($token);
        $this->assertFalse($invitation->isAccepted());

        // Step 3: Accept invitation
        $userData = ['username' => 'workflow_user', 'password' => 'Password123!'];
        $user = $this->invitationService->acceptInvitation($token, $userData);

        // Assert
        $this->assertEquals('workflow@example.com', $user->email);
        $this->assertTrue($user->hasRole('member'));
        $this->assertDatabaseHas('user_invitations', [
            'email' => 'workflow@example.com',
            'accepted_at' => now()->toDateTimeString(),
        ]);
    }
}

