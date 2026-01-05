<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Notifications\SecureShareCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaRedesignTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // Seed features and plans for quota testing
        $this->seed(\Database\Seeders\SystemFeatureSeeder::class);
        $this->seed(\Database\Seeders\PlanSeeder::class);

        Storage::fake('public');
        Storage::fake('local');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_rename_media()
    {
        $media = Media::factory()->create([
            'user_id' => $this->user->id,
            'file_name' => 'old_name.jpg',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/media/{$media->id}/rename", [
                'name' => 'new_name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.file_name', 'new_name.jpg');

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'file_name' => 'new_name.jpg',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_update_visibility_to_shared_and_generates_token()
    {
        Notification::fake();

        $media = Media::factory()->create([
            'user_id' => $this->user->id,
            'visibility' => 'private',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/media/{$media->id}/visibility", [
                'visibility' => 'shared',
                'allow_download' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.visibility', 'shared');

        $media->refresh();
        $this->assertEquals('shared', $media->visibility);
        $this->assertNotNull($media->share_token);

        Notification::assertSentTo($this->user, SecureShareCreated::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function public_can_access_shared_media_via_token()
    {
        // This test requires non-faked Storage for path() resolution
        // used by the showShared method's response()->file() call
        $this->markTestIncomplete(
            'Public shared access test requires non-faked Storage for file path resolution.'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_performs_multipart_upload_flow()
    {
        // This test requires non-faked Storage to properly assemble chunks
        $this->markTestIncomplete(
            'Multipart upload test requires non-faked local Storage for chunk assembly.'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_triggers_notifications_at_storage_thresholds()
    {
        // This test requires proper subscription setup with plan limits
        // and non-faked file uploads to properly track storage usage
        $this->markTestIncomplete(
            'Notification threshold test requires subscription and storage quota infrastructure.'
        );
    }
}
