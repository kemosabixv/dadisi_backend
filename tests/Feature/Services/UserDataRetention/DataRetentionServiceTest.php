<?php

namespace Tests\Feature\Services\UserDataRetention;

use App\Models\User;
use App\Models\MemberProfile;
use App\Services\UserDataRetention\DataRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * DataRetentionServiceTest
 *
 * Test suite for DataRetentionService covering:
 * - User data anonymization
 * - Data export
 * - Data archival
 * - Retention policies
 */
class DataRetentionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DataRetentionService $service;
    private User $admin;
    private User $targetUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DataRetentionService::class);
        $this->admin = User::factory()->create(['username' => 'admin_user']);
        $this->targetUser = User::factory()->create(['username' => 'target_user']);
    }

    // ============ Anonymization Tests ============

    #[Test]
    public function it_can_anonymize_user_data(): void
    {
        $this->targetUser->update([
            'username' => 'john_doe',
            'email' => 'john@example.com',
        ]);

        $result = $this->service->anonymizeUserData($this->targetUser);

        $this->assertTrue($result);
        
        $fresh = $this->targetUser->fresh();
        $this->assertStringContainsString('deleted', $fresh->email);
        $this->assertStringContainsString('deleted-user', $fresh->username);
    }

    #[Test]
    public function it_preserves_user_id_on_anonymization(): void
    {
        $userId = $this->targetUser->id;

        $this->service->anonymizeUserData($this->targetUser);

        $fresh = User::find($userId);
        $this->assertNotNull($fresh);
        $this->assertEquals($userId, $fresh->id);
    }

    #[Test]
    public function it_anonymizes_profile_data_when_present(): void
    {
        // Create profile with all required fields
        MemberProfile::create([
            'user_id' => $this->targetUser->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'bio' => 'This is my bio',
            'phone_number' => '+254712345678',
        ]);

        $this->service->anonymizeUserData($this->targetUser);

        $fresh = $this->targetUser->fresh();
        $this->assertNull($fresh->profile->bio);
        $this->assertNull($fresh->profile->phone_number);
        $this->assertEquals('Deleted', $fresh->profile->first_name);
        $this->assertEquals('User', $fresh->profile->last_name);
    }

    #[Test]
    public function it_creates_audit_log_on_anonymization(): void
    {
        $this->service->anonymizeUserData($this->targetUser);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->targetUser->id,
            'action' => 'user_data_anonymized',
        ]);
    }

    #[Test]
    public function it_revokes_user_tokens_on_anonymization(): void
    {
        // Create a token for the user
        $this->targetUser->createToken('test-token');
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $this->targetUser->id,
        ]);

        $this->service->anonymizeUserData($this->targetUser);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->targetUser->id,
        ]);
    }

    // ============ Data Export Tests ============

    #[Test]
    public function it_can_export_user_data(): void
    {
        $data = $this->service->exportUserData($this->targetUser);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('exported_at', $data);
    }

    #[Test]
    public function it_includes_profile_in_export_when_present(): void
    {
        MemberProfile::create([
            'user_id' => $this->targetUser->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'bio' => 'My bio',
        ]);

        $data = $this->service->exportUserData($this->targetUser->fresh());

        $this->assertArrayHasKey('profile', $data);
        $this->assertNotNull($data['profile']);
        $this->assertEquals('My bio', $data['profile']['bio']);
    }

    #[Test]
    public function it_handles_null_profile_in_export(): void
    {
        $data = $this->service->exportUserData($this->targetUser);

        $this->assertArrayHasKey('profile', $data);
        $this->assertNull($data['profile']);
    }

    #[Test]
    public function it_includes_roles_in_export(): void
    {
        $data = $this->service->exportUserData($this->targetUser);

        $this->assertArrayHasKey('roles', $data);
    }

    #[Test]
    public function it_includes_permissions_in_export(): void
    {
        $data = $this->service->exportUserData($this->targetUser);

        $this->assertArrayHasKey('permissions', $data);
    }

    #[Test]
    public function it_includes_username_in_export(): void
    {
        $this->targetUser->update(['username' => 'test_export_user']);
        
        $data = $this->service->exportUserData($this->targetUser->fresh());

        $this->assertEquals('test_export_user', $data['user']['username']);
    }

    // ============ Archive Tests ============

    #[Test]
    public function it_can_archive_user_data(): void
    {
        Storage::fake('local');

        $path = $this->service->archiveUserData($this->targetUser);

        $this->assertStringContainsString('archives/', $path);
        $this->assertStringContainsString((string) $this->targetUser->id, $path);
        Storage::disk('local')->assertExists($path);
    }

    #[Test]
    public function it_includes_profile_in_archive(): void
    {
        Storage::fake('local');

        MemberProfile::create([
            'user_id' => $this->targetUser->id,
            'first_name' => 'Archive',
            'last_name' => 'Test',
        ]);

        $path = $this->service->archiveUserData($this->targetUser->fresh());

        $content = json_decode(Storage::disk('local')->get($path), true);
        $this->assertArrayHasKey('profile', $content);
        $this->assertEquals('Archive', $content['profile']['first_name']);
    }

    // ============ Policy Tests ============

    #[Test]
    public function it_can_list_retention_policies(): void
    {
        $policies = $this->service->listPolicies();

        $this->assertIsArray($policies);
    }

    #[Test]
    public function it_can_get_retention_summary(): void
    {
        $summary = $this->service->getSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('total_policies', $summary);
        $this->assertArrayHasKey('soft_deleted_users', $summary);
    }

    #[Test]
    public function it_can_list_schedulers(): void
    {
        $schedulers = $this->service->listSchedulers();

        $this->assertIsArray($schedulers);
        $this->assertNotEmpty($schedulers);
    }

    #[Test]
    public function it_can_update_retention_days(): void
    {
        $result = $this->service->updateRetentionDays('profile_data', [
            'retention_days' => 180,
        ]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    #[Test]
    public function it_can_update_scheduler(): void
    {
        $result = $this->service->updateScheduler([
            'id' => 1,
            'enabled' => true,
            'schedule' => '0 3 * * *',
        ]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('0 3 * * *', $result['schedule']);
    }
}
