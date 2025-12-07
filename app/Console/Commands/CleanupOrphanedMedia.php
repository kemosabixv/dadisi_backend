<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CleanupOrphanedMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:cleanup
                            {--days=90 : Age in days after which orphaned media will be removed}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove orphaned media files older than the configured retention period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Starting orphaned media cleanup (older than {$days} days){($dryRun ? ' - DRY RUN' : '')}");

        $cutoff = Carbon::now()->subDays($days)->toDateTimeString();

        // Orphaned = not attached to any model (attached_to is null) and older than cutoff
        $query = Media::whereNull('attached_to')
            ->where('created_at', '<=', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No orphaned media found.');
            return 0;
        }

        $this->info("Found {$count} orphaned media items.");

        if ($dryRun) {
            $this->line('Dry run: no files will be deleted.');
            $query->chunk(100, function ($items) {
                foreach ($items as $m) {
                    $this->line("[DRY] Would remove: {$m->id} - {$m->file_path}");
                }
            });
            return 0;
        }

        $deleted = 0;
        $failed = 0;

        $query->chunkById(100, function ($items) use (&$deleted, &$failed) {
            foreach ($items as $media) {
                try {
                    // Attempt to delete file from storage
                    if ($media->file_path && Storage::exists($media->file_path)) {
                        Storage::delete($media->file_path);
                    }

                    // Permanently remove DB record (force delete) to avoid soft-deleted orphans lingering
                    if (method_exists($media, 'forceDelete')) {
                        $media->forceDelete();
                    } else {
                        $media->delete();
                    }

                    Log::info('Orphaned media removed', ['media_id' => $media->id, 'path' => $media->file_path]);
                    $deleted++;
                } catch (\Exception $e) {
                    Log::error('Failed to remove orphaned media', ['media_id' => $media->id ?? null, 'error' => $e->getMessage()]);
                    $failed++;
                }
            }
        });

        $this->info("Completed. Deleted: {$deleted}. Failed: {$failed}.");
        return 0;
    }
}
