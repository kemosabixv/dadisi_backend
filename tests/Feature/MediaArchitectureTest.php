<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use App\Services\Contracts\MediaServiceContract;
use App\Services\SubscriptionCoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery\MockInterface;
use Mockery;

class MediaArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->withoutExceptionHandling();
        
        Storage::fake('public');
        Storage::fake('r2');
        Storage::fake('local');
        
        $this->seed(\Database\Seeders\SystemFeatureSeeder::class);
        $this->seed(\Database\Seeders\PlanSeeder::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function public_uploads_use_tiered_hierarchy_and_skip_quota()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['slug' => 'summer-fest-2026', 'created_by' => $user->id]);
        
        // Mock a quota for OTHER features, but ensure storage is enough for the file
        $this->mock(SubscriptionCoreService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getFeatureValue')
                 ->with(Mockery::any(), 'media-max-upload-mb')
                 ->andReturn(10); // 10MB limit
            $mock->shouldReceive('getFeatureValue')
                 ->with(Mockery::any(), 'media-storage-mb')
                 ->andReturn(1); // 1MB total storage limit
        });

        // 1. Upload a file to the tiered public path
        // Using context metadata
        $file = UploadedFile::fake()->create('event-banner.jpg', 2048); // 2MB (exceeds 1MB quota)
        
        $response = $this->actingAs($user)
            ->postJson(route('media.store'), [
                'file' => $file,
                'root_type' => 'public',
                'path' => ['events', $event->slug]
            ]);

        // Should succeed because public is exempt
        $response->assertStatus(201);
        
        $mediaId = $response->json('data.id');
        $media = Media::find($mediaId);
        
        // Path should match /Public/events/summer-fest-2026/
        $this->assertEquals('public', $media->folder->root_type);
        $this->assertEquals('summer-fest-2026', $media->folder->name);
        $this->assertEquals('events', $media->folder->parent->name);
        
        // Verify it didn't count against quota internally (logic check)
        $this->assertEquals('public', $media->visibility);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function updating_entity_slug_renames_media_folder()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['slug' => 'old-slug', 'created_by' => $user->id]);
        
        // Mock MediaService to verify renameFolder call
        $mediaServiceMock = $this->mock(MediaServiceContract::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('renameFolder')
                ->once()
                ->with(Mockery::on(fn($actor) => $actor->id === $user->id), 'public', ['events', 'old-slug'], 'new-slug')
                ->andReturn(true);
            
            // Allow other calls (like creating folders)
            $mock->shouldIgnoreMissing();
        });

        // Update slug
        $event->update(['slug' => 'new-slug']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function multipart_upload_supports_public_context_and_quota_skip()
    {
        $user = User::factory()->create();
        
        // Mock small quota
        $this->mock(SubscriptionCoreService::class, function (MockInterface $mock) {
             $mock->shouldReceive('getFeatureValue')
                 ->with(Mockery::any(), 'media-max-upload-mb')
                 ->andReturn(10); // 10MB limit
            $mock->shouldReceive('getFeatureValue')
                 ->with(Mockery::any(), 'media-storage-mb')
                 ->andReturn(1); // 1MB total storage limit
        });

        // 1. Init multipart with public context
        $response = $this->actingAs($user)
            ->postJson(route('media.multipart.init'), [
                'file_name' => 'giant-video.mp4',
                'total_size' => 5 * 1024 * 1024, // 5MB
                'mime_type' => 'video/mp4',
                'root_type' => 'public',
                'path' => ['events', 'promo-video']
            ]);

        $response->assertStatus(200);
        $uploadId = $response->json('data.upload_id');

        // 2. Upload a small chunk
        $chunk = UploadedFile::fake()->create('chunk.mp4', 100);
        $this->actingAs($user)
            ->postJson(route('media.multipart.chunk', $uploadId), [
                'chunk_index' => 0,
                'chunk' => $chunk
            ])->assertStatus(200);

        // 3. Complete
        $response = $this->actingAs($user)
            ->postJson(route('media.multipart.complete', $uploadId), [
                'file_name' => 'giant-video.mp4',
                'mime_type' => 'video/mp4'
            ]);

        $response->assertStatus(201);
        
        $media = Media::find($response->json('data.id'));
        $this->assertEquals('public', $media->visibility);
        $this->assertEquals('promo-video', $media->folder->name);
    }
}
