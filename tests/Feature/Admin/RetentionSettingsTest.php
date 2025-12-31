<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserDataRetentionSetting;

class RetentionSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private UserDataRetentionSetting $retentionSetting;

    protected $shouldSeedRoles = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');

        // Create retention settings for testing
        UserDataRetentionSetting::create([
            'data_type' => 'user_accounts',
            'retention_days' => 90,
            'auto_delete' => true,
            'description' => 'Test retention setting',
        ]);

        // Create additional retention settings for the updateRetentionDays test
        foreach (['orphaned_media', 'audit_logs', 'session_data', 'failed_jobs'] as $type) {
            UserDataRetentionSetting::create([
                'data_type' => $type,
                'retention_days' => 30,
                'auto_delete' => true,
                'description' => "Test $type setting",
            ]);
        }

        $this->retentionSetting = UserDataRetentionSetting::where('data_type', 'user_accounts')->first();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function retention_settings_index_requires_authentication()
    {
        $response = $this->getJson('/api/admin/retention-settings');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function super_admin_can_list_retention_settings()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/retention-settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'data_type',
                        'retention_days',
                    ]
                ]
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function retention_settings_show_requires_authentication()
    {
        $response = $this->getJson('/api/admin/retention-settings/1');

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function super_admin_can_view_single_retention_setting()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/retention-settings/' . $this->retentionSetting->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'data_type',
                    'retention_days',
                ]
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function retention_settings_show_returns_404_for_nonexistent()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/retention-settings/99999');

        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function retention_settings_update_requires_authentication()
    {
        $response = $this->putJson('/api/admin/retention-settings/1', [
            'retention_days' => 90,
        ]);

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function super_admin_can_update_retention_setting()
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson('/api/admin/retention-settings/' . $this->retentionSetting->id, [
                'retention_days' => 120,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.retention_days', 120);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function retention_settings_update_validates_retention_days()
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson('/api/admin/retention-settings/' . $this->retentionSetting->id, [
                'retention_days' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['retention_days']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_admin_cannot_access_retention_settings()
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $response = $this->actingAs($user)
            ->getJson('/api/admin/retention-settings');

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function super_admin_can_update_retention_days()
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/admin/retention-settings/update-days', [
                'data_type' => 'audit_logs',
                'retention_days' => 365,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function update_retention_days_validates_input()
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/admin/retention-settings/update-days', [
                'data_type' => 'invalid_type',
                'retention_days' => 'not_a_number',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data_type', 'retention_days']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function super_admin_can_get_retention_summary()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/retention-settings-summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }
}
