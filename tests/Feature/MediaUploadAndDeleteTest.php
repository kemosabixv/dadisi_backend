<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Models\UserDataRetentionSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for media upload and deletion with temporary media handling
 */
class MediaUploadAndDeleteTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Storage::fake('public');
        
        // Set retention to 1 day for testing
        UserDataRetentionSetting::create([
            'data_type' => 'temporary_media',
            'retention_days' => 1,
            'retention_minutes' => 1440, // 1 day in minutes
            'auto_delete' => true,
        ]);
    }

    /** @test */
    public function user_can_upload_a_media_file()
    {
        $file = UploadedFile::fake()->image('test.jpg', 640, 480);

        $response = $this->actingAs($this->user)->postJson('/api/media', [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['id', 'file_name', 'file_path', 'type', 'mime_type']]);
        $this->assertDatabaseHas('media', [
            'file_name' => 'test.jpg',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function upload_marks_media_as_temporary_when_temporary_flag_set()
    {
        $file = UploadedFile::fake()->image('temp-image.jpg', 640, 480);

        $response = $this->actingAs($this->user)->postJson('/api/media', [
            'file' => $file,
            'temporary' => 1,
        ]);

        $response->assertStatus(201);
        $media = Media::latest()->first();
        
        $this->assertNotNull($media->temporary_until);
        $this->assertTrue($media->temporary_until->isFuture());
    }

    /** @test */
    public function upload_without_temporary_flag_creates_permanent_media()
    {
        $file = UploadedFile::fake()->image('permanent.jpg', 640, 480);

        $response = $this->actingAs($this->user)->postJson('/api/media', [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $media = Media::latest()->first();
        
        $this->assertNull($media->temporary_until);
    }

    /** @test */
    public function user_can_delete_temporary_media_without_force()
    {
        $media = Media::factory()->for($this->user)->create([
            'temporary_until' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/media/{$media->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    /** @test */
    public function user_cannot_delete_permanent_media_without_force()
    {
        $media = Media::factory()->for($this->user)->create([
            'temporary_until' => null, // Permanent
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/media/{$media->id}");

        $response->assertStatus(422);
        // Check for validation error about deletion requirement
        $this->assertTrue($response->json('errors') !== null, 'Response should have validation errors');
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    /** @test */
    public function user_can_force_delete_permanent_media()
    {
        $media = Media::factory()->for($this->user)->create([
            'temporary_until' => null,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/media/{$media->id}", [
            'force' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    /** @test */
    public function user_cannot_delete_another_users_media()
    {
        $otherUser = User::factory()->create();
        $media = Media::factory()->for($otherUser)->create([
            'temporary_until' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/media/{$media->id}");

        $response->assertStatus(403);
        // Verify media still exists (not deleted)
        $this->assertDatabaseHas('media', ['id' => $media->id, 'deleted_at' => null]);
    }

    /** @test */
    public function user_can_list_their_media()
    {
        Media::factory()->for($this->user)->count(3)->create();
        Media::factory()->for(User::factory())->count(2)->create();

        $response = $this->actingAs($this->user)->getJson('/api/media');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    /** @test */
    public function media_list_can_be_filtered_by_type()
    {
        Media::factory()->for($this->user)->create(['type' => 'image']);
        Media::factory()->for($this->user)->create(['type' => 'pdf']);

        $response = $this->actingAs($this->user)->getJson('/api/media?type=image');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals('image', $response->json('data.0.type'));
    }

    /** @test */
    public function media_list_can_be_searched_by_filename()
    {
        Media::factory()->for($this->user)->create(['file_name' => 'important.pdf']);
        Media::factory()->for($this->user)->create(['file_name' => 'random.txt']);

        $response = $this->actingAs($this->user)->getJson('/api/media?search=important');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals('important.pdf', $response->json('data.0.file_name'));
    }

    /** @test */
    public function unauthenticated_user_cannot_upload_media()
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->postJson('/api/media', ['file' => $file]);

        $response->assertStatus(401);
    }

    /** @test */
    public function upload_rejects_unsupported_file_types()
    {
        $file = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($this->user)->postJson('/api/media', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function upload_rejects_oversized_files()
    {
        // Create a fake file larger than 5MB
        $file = UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg');

        $response = $this->actingAs($this->user)->postJson('/api/media', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }
}
