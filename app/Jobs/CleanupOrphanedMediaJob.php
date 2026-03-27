<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\Contracts\MediaServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CleanupOrphanedMediaJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(MediaServiceContract $mediaService): void
    {
        Log::info('Starting orphaned media cleanup...');

        $orphanedCount = 0;
        $freedBytes = 0;

        // Pull retention settings from DB
        $retentionDays = \App\Models\UserDataRetentionSetting::getRetentionDays('temporary_media');

        // 1. Process Expired Temporary Media (Explicitly tagged)
        Media::whereNotNull('temporary_until')
            ->where('temporary_until', '<', now())
            ->chunk(100, function ($mediaItems) use ($mediaService, &$orphanedCount, &$freedBytes) {
                foreach ($mediaItems as $media) {
                    $freedBytes += $media->file_size;
                    $this->deleteOrphan($media, $mediaService);
                    $orphanedCount++;
                }
            });

        // 2. Process Unattached Public Media (Likely abandoned drafts)
        // Only clean up media in /Public/ folders that is older than the retention setting
        Media::where('usage_count', 0)
            ->whereHas('folder', fn($q) => $q->where('root_type', 'public'))
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->whereNotExists(function($query) {
                $query->select(\Illuminate\Support\Facades\DB::raw(1))
                      ->from('media_attachments')
                      ->whereColumn('media_attachments.media_id', 'media.id');
            })
            ->chunk(100, function ($mediaItems) use ($mediaService, &$orphanedCount, &$freedBytes) {
                foreach ($mediaItems as $media) {
                    $freedBytes += $media->file_size;
                    $this->deleteOrphan($media, $mediaService);
                    $orphanedCount++;
                }
            });

        // NOTE: Step 3 (Personal Media Cleanup) has been removed.
        // Personal storage media is persistent user storage and should not be automatically deleted.

        Log::info("Cleanup finished. Removed {$orphanedCount} media items. Freed " . round($freedBytes / 1024 / 1024, 2) . " MB.");
    }

    protected function deleteOrphan(Media $media, MediaServiceContract $mediaService): void
    {
        try {
            $owner = $media->owner;
            if ($owner) {
                $mediaService->deleteMedia($owner, $media);
            } else {
                // If no owner, force delete via model
                $media->forceDelete();
            }
        } catch (\Exception $e) {
            Log::error("Failed to delete orphaned media {$media->id}: " . $e->getMessage());
        }
    }
}
