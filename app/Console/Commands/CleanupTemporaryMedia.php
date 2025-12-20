<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Media;
use Carbon\Carbon;

class CleanupTemporaryMedia extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'media:cleanup-temporary';

    /**
     * The console command description.
     */
    protected $description = 'Delete temporary media files that have expired (not associated with a saved post)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();
        $expired = Media::whereNotNull('temporary_until')
            ->where('temporary_until', '<', $now)
            ->get();

        $deleted = 0;
        foreach ($expired as $media) {
            // Delete file from storage
            if ($media->file_path) {
                \Storage::disk('public')->delete(ltrim($media->file_path, '/'));
            }
            $media->forceDelete();
            $deleted++;
        }

        $this->info("Deleted {$deleted} expired temporary media files.");
        return 0;
    }
}
