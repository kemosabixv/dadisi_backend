<?php

namespace Tests\Feature\Admin;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Create an admin user and assign the admin role
        \Spatie\Permission\Models\Role::create(['name' => 'admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    #[Test]
    public function it_can_list_system_settings()
    {
        SystemSetting::create(['key' => 'general.site_name', 'value' => 'Dadisi', 'type' => 'string']);
        SystemSetting::create(['key' => 'pesapal.enabled', 'value' => '1', 'type' => 'boolean']);

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->getJson('/api/admin/system-settings');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'general.site_name' => 'Dadisi',
                         'pesapal.enabled' => true
                     ]
                 ]);
    }

    #[Test]
    public function it_can_filter_settings_by_group()
    {
        SystemSetting::create(['key' => 'general.site_name', 'value' => 'Dadisi', 'group' => 'general']);
        SystemSetting::create(['key' => 'pesapal.enabled', 'value' => '1', 'group' => 'pesapal']);

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->getJson('/api/admin/system-settings?group=pesapal');

        $response->assertStatus(200)
                 ->assertJsonFragment(['pesapal.enabled' => '1']) // Note: Cast might depend on how it's saved. If type missing, defaults string.
                 ->assertJsonMissing(['general.site_name' => 'Dadisi']);
    }

    #[Test]
    public function it_can_bulk_update_settings()
    {
        $payload = [
            'pesapal.consumer_key' => 'new_secret_key',
            'pesapal.live_mode' => true,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->putJson('/api/admin/system-settings', $payload);

        $response->assertStatus(200)
                 ->assertJson([
                     'data' => [
                         'pesapal.consumer_key' => 'new_secret_key',
                         'pesapal.live_mode' => true,
                     ]
                 ]);

        $this->assertDatabaseHas('system_settings', [
            'key' => 'pesapal.consumer_key',
            'value' => 'new_secret_key'
        ]);
    }
}
