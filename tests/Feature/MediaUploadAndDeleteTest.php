<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Models\UserDataRetentionSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
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
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('member');
        Storage::fake('public');
        
        // Set retention to 1 day for testing
        UserDataRetentionSetting::create([
            'data_type' => 'temporary_media',
            'retention_days' => 1,
            'retention_minutes' => 1440, // 1 day in minutes
            'auto_delete' => true,
        ]);
    }

    private function authenticatedRequest(User $user, string $method, string $uri, array $data = [], array $files = []): TestResponse
    {
        return $this->actingAs($user)->json($method, $uri, $data, $files);
    }

    #[Test]
    public function user_can_upload_a_media_file()
    {
        $file = UploadedFile::fake()->image('test.jpg', 640, 480);

        $response = $this->authenticatedRequest($this->user, 'POST', '/api/media', [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['id', 'file_name', 'file_path', 'type', 'mime_type']]);
        $this->assertDatabaseHas('media', [
            'file_name' => 'test.jpg',
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function upload_marks_media_as_temporary_when_temporary_flag_set()
    {
        $file = UploadedFile::fake()->image('temp-image.jpg', 640, 480);

        $response = $this->authenticatedRequest($this->user, 'POST', '/api/media', [
            'file' => $file,
            'temporary' => 1,
        ]);

        $response->assertStatus(201);
        $media = Media::latest()->first();
        
        $this->assertNotNull($media->temporary_until);
        $this->assertTrue($media->temporary_until->isFuture());
    }

    #[Test]
    public function upload_without_temporary_flag_creates_permanent_media()
    {
        $file = UploadedFile::fake()->image('permanent.jpg', 640, 480);

        $response = $this->authenticatedRequest($this->user, 'POST', '/api/media', [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $media = Media::latest()->first();
        
        $this->assertNull($media->temporary_until);
    }

    #[Test]
    public function user_can_delete_temporary_media_without_force()
    {
        $media = Media::factory()->for($this->user)->create([
            'temporary_until' => now()->addDay(),
        ]);

        $response = $this->authenticatedRequest($this->user, 'DELETE', "/api/media/{$media->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    #[Test]
    public function user_can_delete_permanent_media_without_force_when_unused()
    {
        $media = Media::factory()->for($this->user)->create([
            'temporary_until' => null,
            'usage_count' => 0,
        ]);

        $response = $this->authenticatedRequest($this->user, 'DELETE', "/api/media/{$media->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    #[Test]
    public function user_cannot_delete_permanent_media_without_force_when_in_use()
    {
        $media = Media::factory()->for($this->user)->create([
            'temporary_until' => null,
            'usage_count' => 1,
        ]);

        $response = $this->authenticatedRequest($this->user, 'DELETE', "/api/media/{$media->id}");

        $response->assertStatus(422);
        $this->assertTrue($response->json('errors') !== null, 'Response should have validation errors');
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    #[Test]
    public function user_cannot_delete_permanent_media_in_use_even_with_force()
    {
        $media = Media::factory()->for($this->user)->create([
            'temporary_until' => null,
            'usage_count' => 1,
        ]);

        // Force query param should be ignored and deletion blocked when in use
        $response = $this->authenticatedRequest($this->user, 'DELETE', "/api/media/{$media->id}?force=1");

        $response->assertStatus(422);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    #[Test]
    public function user_cannot_delete_another_users_media()
    {
        $otherUser = User::factory()->create();
        $media = Media::factory()->for($otherUser)->create([
            'temporary_until' => now()->addDay(),
        ]);

        $response = $this->authenticatedRequest($this->user, 'DELETE', "/api/media/{$media->id}");

        $response->assertStatus(403);
        // Verify media still exists (not deleted)
        $this->assertDatabaseHas('media', ['id' => $media->id, 'deleted_at' => null]);
    }

    #[Test]
    public function user_can_list_their_media()
    {
        Media::factory()->for($this->user)->count(3)->create();
        Media::factory()->for(User::factory())->count(2)->create();

        $response = $this->authenticatedRequest($this->user, 'GET', '/api/media');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    #[Test]
    public function media_list_can_be_filtered_by_type()
    {
        Media::factory()->for($this->user)->create(['type' => 'image']);
        Media::factory()->for($this->user)->create(['type' => 'pdf']);

        $response = $this->authenticatedRequest($this->user, 'GET', '/api/media?type=image');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals('image', $response->json('data.0.type'));
    }

    #[Test]
    public function media_list_can_be_searched_by_filename()
    {
        Media::factory()->for($this->user)->create(['file_name' => 'important.pdf']);
        Media::factory()->for($this->user)->create(['file_name' => 'random.txt']);

        $response = $this->authenticatedRequest($this->user, 'GET', '/api/media?search=important');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals('important.pdf', $response->json('data.0.file_name'));
    }

    #[Test]
    public function unauthenticated_user_cannot_upload_media()
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->postJson('/api/media', ['file' => $file]);

        $response->assertStatus(401);
    }

    #[Test]
    public function upload_rejects_unsupported_file_types()
    {
        $file = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($this->user)->postJson('/api/media', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
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
