<?php

namespace Tests\Feature;

use App\Jobs\CleanupTemporaryMediaJob;
use App\Models\Media;
use App\Models\MediaFile;
use App\Models\User;
use App\Models\UserDataRetentionSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_temporary_media_deletes_expired_entries_and_cas_file()
    {
        Storage::fake('r2');

        $user = User::factory()->create();

        $mediaFile = MediaFile::create([
            'hash' => 'deadbeef',
            'disk' => 'r2',
            'path' => 'blobs/deadbeef',
            'size' => 1024,
            'mime_type' => 'image/jpeg',
            'ref_count' => 1,
        ]);

        Storage::disk('r2')->put($mediaFile->path, 'abc');

        $media = Media::factory()->create([
            'user_id' => $user->id,
            'media_file_id' => $mediaFile->id,
            'temporary_until' => now()->subDays(2),
            'usage_count' => 0,
        ]);

        $this->artisan('media:cleanup-temporary', ['--sync' => true, '--days' => 1])
             ->assertExitCode(0);

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        $this->assertDatabaseMissing('media_files', ['id' => $mediaFile->id]);
        Storage::disk('r2')->assertMissing($mediaFile->path);
    }

    public function test_cleanup_temporary_media_does_not_delete_in_use_media()
    {
        Storage::fake('r2');

        $user = User::factory()->create();

        $mediaFile = MediaFile::create([
            'hash' => 'beefdead',
            'disk' => 'r2',
            'path' => 'blobs/beefdead',
            'size' => 2048,
            'mime_type' => 'image/jpeg',
            'ref_count' => 1,
        ]);

        Storage::disk('r2')->put($mediaFile->path, 'xyz');

        $media = Media::factory()->create([
            'user_id' => $user->id,
            'media_file_id' => $mediaFile->id,
            'temporary_until' => now()->subDays(2),
            'usage_count' => 2,
        ]);

        $this->artisan('media:cleanup-temporary', ['--sync' => true, '--days' => 1])
             ->assertExitCode(0);

        $this->assertDatabaseHas('media', ['id' => $media->id]);
        $this->assertDatabaseHas('media_files', ['id' => $mediaFile->id]);
        Storage::disk('r2')->assertExists($mediaFile->path);
    }
}
