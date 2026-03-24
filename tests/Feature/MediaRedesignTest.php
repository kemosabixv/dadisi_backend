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
        \App\Models\Media::query()->delete();

        // Seed features and plans for quota testing
        $this->seed(\Database\Seeders\SystemFeatureSeeder::class);
        $this->seed(\Database\Seeders\PlanSeeder::class);

        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('r2');
        
        // Ensure testing directories exist (though fake() makes them virtual)
        Storage::disk('public')->makeDirectory('media');
        Storage::disk('local')->makeDirectory('chunks');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_rename_media()
    {
        $media = Media::factory()->create([
            'user_id' => $this->user->id,
            'file_name' => 'old_name.jpg',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson(route('media.update', $media->id), [
                'file_name' => 'new_name.jpg',
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
            ->putJson(route('media.update', $media->id), [
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
        $content = 'fake image binary content';
        $fileName = 'shared_test.jpg';
        $hash = hash('sha256', $content);
        $path = 'blobs/test/' . $hash;
        
        Storage::disk('r2')->put($path, $content);

        $mediaFile = \App\Models\MediaFile::factory()->create([
            'hash' => $hash,
            'path' => $path,
            'disk' => 'r2',
        ]);

        $media = Media::factory()->create([
            'user_id' => $this->user->id,
            'media_file_id' => $mediaFile->id,
            'file_name' => $fileName,
            'visibility' => 'shared',
            'share_token' => (string) \Illuminate\Support\Str::uuid(),
            'mime_type' => 'image/jpeg',
        ]);

        $response = $this->getJson(route('media.shared', $media->share_token));

        $response->assertStatus(200);
        $this->assertEquals($content, $response->streamedContent());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_performs_multipart_upload_flow()
    {
        $fileName = 'multipart.pdf';
        $totalSize = 15; // 3 chunks of 5 bytes
        $mimeType = 'application/pdf';

        // 1. Initialize
        $response = $this->actingAs($this->user)
            ->postJson(route('media.multipart.init'), [
                'file_name' => $fileName,
                'total_size' => $totalSize,
                'mime_type' => $mimeType,
            ]);

        $response->assertStatus(200);
        $uploadId = $response->json('data.upload_id');

        // 2. Upload chunks
        for ($i = 0; $i < 3; $i++) {
            $chunkContent = ($i === 0) ? "%PDF-" : str_repeat(chr(65 + $i), 5); 
            $chunk = \Illuminate\Http\UploadedFile::fake()->create("chunk_{$i}.pdf", 5, 'application/pdf');
            
            // We need to overwrite the fake content since we want to verify assembly
            file_put_contents($chunk->getRealPath(), $chunkContent);

            $response = $this->actingAs($this->user)
                ->postJson(route('media.multipart.chunk', $uploadId), [
                    'chunk_index' => $i,
                    'chunk' => $chunk,
                ]);
            $response->assertStatus(200);
        }

        // 3. Complete
        $response = $this->actingAs($this->user)
            ->postJson(route('media.multipart.complete', $uploadId), [
                'file_name' => $fileName,
                'mime_type' => $mimeType,
            ]);

        $response->assertStatus(201);
        $mediaId = $response->json('data.id');

        // 4. Verify assembly
        $media = Media::find($mediaId);
        $assembledContent = Storage::disk($media->file->disk)->get($media->file->path);
        
        $this->assertEquals("%PDF-BBBBBCCCCC", $assembledContent);
        $this->assertEquals($totalSize, $media->file_size);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_triggers_notifications_at_storage_thresholds()
    {
        Notification::fake();
        
        // Mock a 10MB quota for testing
        $this->mock(\App\Services\SubscriptionCoreService::class, function ($mock) {
            $mock->shouldReceive('getFeatureValue')
                ->withAnyArgs()
                ->andReturn(10); // 10 MB
        });

        // 1. Upload file that takes us to 85% (8.5MB)
        $file85 = \Illuminate\Http\UploadedFile::fake()->create('large.jpg', 8.5 * 1024, 'image/jpeg');
        $response = $this->actingAs($this->user)
            ->postJson(route('media.store'), ['file' => $file85]);
        
        $response->assertStatus(201);

        Notification::assertSentTo($this->user, \App\Notifications\StorageUsageAlert::class, function ($notification) {
            return $notification->percentage === 80;
        });

        // 2. Upload file that takes us over 100% (extra 2MB = 10.5MB total)
        // Wait, the Service validates quota and SHOULD FAIL the upload if it exceeds.
        // Let's check the Service logic. It throws MediaException::validationFailed.
        
        $fileOver = \Illuminate\Http\UploadedFile::fake()->create('overflow.jpg', 2 * 1024);
        $response = $this->actingAs($this->user)
            ->postJson('/api/media', ['file' => $fileOver]);

        $response->assertStatus(422); // Validation failed due to quota
        Notification::assertSentTo($this->user, \App\Notifications\StorageUsageAlert::class);
        
        // However, the checkAndNotifyStorageThresholds is called BEFORE the record is created 
        // but AFTER the file is validated? Actually, uploadMedia calls validateFile first.
        // If validateFile fails, it throws.
        
        // Let's re-read MediaService.php:131. It calls checkAndNotifyStorageThresholds 
        // AFTER the record is created in the transaction. 
        // But validateFile at line 82 also checks quota!
        
        // So we can't actually "exceed" the quota in uploadMedia because validateFile blocks it.
        // But we can REACH 100%.
        
        $file100 = \Illuminate\Http\UploadedFile::fake()->create('full.jpg', 1.5 * 1024, 'image/jpeg'); // 8.5 + 1.5 = 10MB
        $response = $this->actingAs($this->user)
            ->postJson(route('media.store'), ['file' => $file100]);

        $response->assertStatus(201);

        Notification::assertSentTo($this->user, \App\Notifications\StorageQuotaExceeded::class);
    }
}
