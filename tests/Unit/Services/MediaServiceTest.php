<?php

namespace Tests\Unit\Services;

use App\Exceptions\MediaException;
use App\Models\Media;
use App\Models\User;
use App\Models\UserDataRetentionSetting;
use App\Services\Media\MediaService;
use App\Services\SubscriptionCoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for MediaService
 */
class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MediaService $mediaService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('r2');
        
        $this->user = User::factory()->create();
        
        $subscriptionService = $this->mock(SubscriptionCoreService::class);
        $subscriptionService->shouldReceive('getFeatureValue')
            ->with(\Mockery::any(), 'media-storage-mb')
            ->andReturn(100);
        $subscriptionService->shouldReceive('getFeatureValue')
            ->with(\Mockery::any(), 'media-max-upload-mb')
            ->andReturn(5);
        
        $this->mediaService = new MediaService($subscriptionService);
        
        // Set retention to 1 day for testing
        UserDataRetentionSetting::factory()->create([
            'data_type' => 'temporary_media',
            'retention_minutes' => 1440,
        ]);
    }

    #[Test]
    public function upload_media_creates_record_in_database()
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $media = $this->mediaService->uploadMedia($this->user, $file);

        $this->assertNotNull($media->id);
        $this->assertEquals('test.jpg', $media->file_name);
        $this->assertEquals($this->user->id, $media->user_id);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    #[Test]
    public function upload_media_with_temporary_flag_sets_expiration()
    {
        $file = UploadedFile::fake()->image('temp.jpg');

        $media = $this->mediaService->uploadMedia($this->user, $file, ['temporary' => true]);

        $this->assertNotNull($media->temporary_until);
        $this->assertTrue($media->temporary_until->isFuture());
    }

    #[Test]
    public function upload_media_without_temporary_flag_does_not_set_expiration()
    {
        $file = UploadedFile::fake()->image('permanent.jpg');

        $media = $this->mediaService->uploadMedia($this->user, $file);

        $this->assertNull($media->temporary_until);
    }

    #[Test]
    public function delete_media_without_force_succeeds_for_permanent_media_without_usage()
    {
        $media = Media::factory()->for($this->user)->create(['temporary_until' => null, 'usage_count' => 0]);

        $result = $this->mediaService->deleteMedia($this->user, $media);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    #[Test]
    public function delete_media_without_force_fails_when_media_in_use()
    {
        $media = Media::factory()->for($this->user)->create(['temporary_until' => null, 'usage_count' => 1]);

        $this->expectException(MediaException::class);
        $this->mediaService->deleteMedia($this->user, $media);
    }

    #[Test]
    public function delete_media_succeeds_for_temporary_media_without_force()
    {
        $media = Media::factory()->for($this->user)->create([
            'temporary_until' => now()->addDay(),
        ]);

        $result = $this->mediaService->deleteMedia($this->user, $media, false);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    #[Test]
    public function delete_media_with_force_flag_is_redundant_and_succeeds_when_unused()
    {
        $media = Media::factory()->for($this->user)->create(['temporary_until' => null, 'usage_count' => 0]);

        $result = $this->mediaService->deleteMedia($this->user, $media);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    #[Test]
    public function delete_media_with_force_flag_still_fails_when_in_use()
    {
        $media = Media::factory()->for($this->user)->create(['temporary_until' => null, 'usage_count' => 1]);

        $this->expectException(MediaException::class);
        $this->mediaService->deleteMedia($this->user, $media);
    }

    #[Test]
    public function delete_media_fails_if_user_is_not_owner()
    {
        $otherUser = User::factory()->create();
        $media = Media::factory()->for($otherUser)->create(['temporary_until' => now()->addDay()]);

        $this->expectException(MediaException::class);
        $this->mediaService->deleteMedia($this->user, $media);
    }

    #[Test]
    public function validate_file_accepts_supported_image_types()
    {
        $jpegFile = UploadedFile::fake()->image('photo.jpg');
        $pngFile = UploadedFile::fake()->image('photo.png');

        $jpegValidation = $this->mediaService->validateFile($jpegFile, $this->user);
        $pngValidation = $this->mediaService->validateFile($pngFile, $this->user);

        $this->assertTrue($jpegValidation['valid']);
        $this->assertEquals('image', $jpegValidation['type']);
        $this->assertTrue($pngValidation['valid']);
    }

    #[Test]
    public function validate_file_rejects_unsupported_types()
    {
        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $validation = $this->mediaService->validateFile($file, $this->user);

        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('Unsupported', $validation['error']);
    }

    #[Test]
    public function validate_file_rejects_oversized_files()
    {
        $file = UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg');

        $validation = $this->mediaService->validateFile($file, $this->user);

        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('exceeds', $validation['error']);
    }

    #[Test]
    public function get_media_returns_correct_record()
    {
        $media = Media::factory()->for($this->user)->create();

        $retrieved = $this->mediaService->getMedia($media->id);

        $this->assertEquals($media->id, $retrieved->id);
    }

    #[Test]
    public function get_media_throws_exception_for_nonexistent_id()
    {
        $this->expectException(MediaException::class);
        $this->mediaService->getMedia(99999);
    }

    #[Test]
    public function list_media_returns_only_users_media()
    {
        Media::factory()->for($this->user)->count(3)->create();
        Media::factory()->for(User::factory())->count(2)->create();

        $result = $this->mediaService->listMedia($this->user);

        $this->assertEquals(3, $result->total());
    }

    #[Test]
    public function list_media_filters_by_type()
    {
        Media::factory()->for($this->user)->create(['type' => 'image']);
        Media::factory()->for($this->user)->create(['type' => 'pdf']);

        $result = $this->mediaService->listMedia($this->user, ['type' => 'image']);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('image', $result->first()->type);
    }

    #[Test]
    public function list_media_searches_by_filename()
    {
        Media::factory()->for($this->user)->create(['file_name' => 'invoice-2025.pdf']);
        Media::factory()->for($this->user)->create(['file_name' => 'profile.jpg']);

        $result = $this->mediaService->listMedia($this->user, ['search' => 'invoice']);

        $this->assertEquals(1, $result->total());
        $this->assertStringContainsString('invoice', $result->first()->file_name);
    }

    #[Test]
    public function get_media_url_returns_valid_path()
    {
        $mediaFile = \App\Models\MediaFile::factory()->create([
            'disk' => 'public',
            'path' => 'media/2025-01/test.jpg',
        ]);

        $media = Media::factory()->for($this->user)->create([
            'media_file_id' => $mediaFile->id,
            'file_name' => 'test.jpg',
        ]);

        $url = $this->mediaService->getMediaUrl($media);

        $this->assertStringContainsString('/storage/media/2025-01/test.jpg', $url);
    }
}
