<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Media;
use App\Models\MediaFile;
use App\Models\User;
use App\Models\UserDataRetentionSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Storage::fake('r2');
        
        // Ensure retention setting exists
        UserDataRetentionSetting::updateOrCreate(
            ['data_type' => 'temporary_media'],
            [
                'retention_days' => 7,
                'auto_delete' => true,
                'is_enabled' => true
            ]
        );
    }

    #[Test]
    public function it_enforces_root_isolation_in_uploads()
    {
        $file = UploadedFile::fake()->image('personal.jpg');

        // Personal upload
        $response = $this->actingAs($this->user)->postJson('/api/media', [
            'file' => $file,
            'root_type' => 'personal'
        ]);

        $response->assertStatus(201);
        $this->assertEquals('personal', $response->json('data.root_type'));

        // Public upload
        $file2 = UploadedFile::fake()->image('public.jpg');
        $response2 = $this->actingAs($this->user)->postJson('/api/media', [
            'file' => $file2,
            'root_type' => 'public'
        ]);

        $response2->assertStatus(201);
        $this->assertEquals('public', $response2->json('data.root_type'));
        $this->assertEquals('public', $response2->json('data.visibility'));
    }

    #[Test]
    public function it_blocks_public_to_personal_moves()
    {
        $media = Media::factory()->create([
            'user_id' => $this->user->id,
            'root_type' => 'public',
            'visibility' => 'public'
        ]);

        $personalFolder = Folder::create([
            'user_id' => $this->user->id,
            'name' => 'My Personal Folder',
            'root_type' => 'personal'
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/media/{$media->id}", [
            'folder_id' => $personalFolder->id
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('not allowed', $response->json('message'));
    }

    #[Test]
    public function it_sets_temporary_expiry_on_manual_move_to_public()
    {
        $media = Media::factory()->create([
            'user_id' => $this->user->id,
            'root_type' => 'personal',
            'visibility' => 'private',
            'temporary_until' => null
        ]);

        $publicFolder = Folder::create([
            'user_id' => $this->user->id,
            'name' => 'Public Assets',
            'root_type' => 'public'
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/media/{$media->id}", [
            'folder_id' => $publicFolder->id
        ]);

        $response->assertStatus(200);
        $media->refresh();

        $this->assertEquals('public', $media->root_type);
        $this->assertEquals('public', $media->visibility);
        $this->assertNotNull($media->temporary_until);
    }

    #[Test]
    public function it_locks_public_media_visibility()
    {
        $media = Media::factory()->create([
            'user_id' => $this->user->id,
            'root_type' => 'public',
            'visibility' => 'public'
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/media/{$media->id}", [
            'visibility' => 'private'
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('cannot be changed', $response->json('message'));
    }

    #[Test]
    public function it_denies_public_visibility_in_personal_root()
    {
        $media = Media::factory()->create([
            'user_id' => $this->user->id,
            'root_type' => 'personal',
            'visibility' => 'private'
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/media/{$media->id}", [
            'visibility' => 'public'
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('cannot be marked as public', $response->json('message'));
    }

    #[Test]
    public function it_filters_media_by_root_type_in_index()
    {
        Media::factory()->create(['user_id' => $this->user->id, 'root_type' => 'personal']);
        Media::factory()->create(['user_id' => $this->user->id, 'root_type' => 'public']);

        // Test personal filter
        $response = $this->actingAs($this->user)->getJson('/api/media?root_type=personal&folder_id=root');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('personal', $response->json('data.0.root_type'));

        // Test public filter
        $response2 = $this->actingAs($this->user)->getJson('/api/media?root_type=public&folder_id=root');
        $response2->assertStatus(200);
        $this->assertCount(1, $response2->json('data'));
        $this->assertEquals('public', $response2->json('data.0.root_type'));
    }
}
