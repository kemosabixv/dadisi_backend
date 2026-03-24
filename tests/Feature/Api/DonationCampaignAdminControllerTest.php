<?php

namespace Tests\Feature\Api;

use App\Models\DonationCampaign;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DonationCampaignAdminControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        Storage::fake('public');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_can_create_campaign_with_hero_image()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $file = UploadedFile::fake()->image('hero.jpg');
        $county = \App\Models\County::first();

        $response = $this->actingAs($admin)
            ->postJson(route('admin.donation-campaigns.store'), [
                'title' => 'Test Campaign',
                'description' => 'Test description',
                'currency' => 'KES',
                'goal_amount' => 100000,
                'county_id' => $county->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(30)->toDateString(),
                'hero_image' => $file,
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertNotEmpty($data['hero_image_url']);
        $this->assertNotEmpty($data['featured_media_id']);
        $this->assertEquals('Test Campaign', $data['title']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_can_update_campaign_hero_image()
    {
        $admin = User::factory()->create();
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
        $admin->refresh();
        $campaign = DonationCampaign::factory()->create([
            'created_by' => $admin->id,
        ]);
        $file = UploadedFile::fake()->image('new-hero.jpg');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $response = $this->actingAs($admin)
            ->putJson(route('admin.donation-campaigns.update', $campaign->slug), [
                'hero_image' => $file,
            ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data['hero_image_url']);
        $this->assertNotEmpty($data['featured_media_id']);
    }
}
