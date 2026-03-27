<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\Event;
use App\Models\DonationCampaign;
use App\Models\User;
use App\Models\Folder;
use App\Services\Contracts\MediaServiceContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrateSeededMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:migrate-seeded {--cleanup : Null out legacy image fields after migration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy seeded media files (Post, Event, DonationCampaign) to CAS/R2 and create virtual pointers.';

    /**
     * Execute the console command.
     *
     * @param MediaServiceContract $mediaService
     * @return int
     */
    public function handle(MediaServiceContract $mediaService)
    {
        // 1. Find a Super Admin or the first user to own these system files
        $admin = User::whereHas('roles', fn($q) => $q->where('name', 'super_admin'))->first() 
                 ?? User::first();

        if (!$admin) {
            $this->error('No admin user found to own the migrated media.');
            return 1;
        }

        $this->info("Using admin user: {$admin->email} (ID: {$admin->id}) as owner.");

        $models = [
            'Post' => [
                'model' => Post::class,
                'query' => Post::whereNotNull('hero_image_path'),
                'fields' => ['hero_image_path'],
                'root' => 'blog-posts',
            ],
            'Event' => [
                'model' => Event::class,
                'query' => Event::whereNotNull('image_path'),
                'fields' => ['image_path'],
                'root' => 'events',
            ],
            'DonationCampaign' => [
                'model' => DonationCampaign::class,
                'query' => DonationCampaign::whereNotNull('hero_image_path'),
                'fields' => ['hero_image_path'],
                'root' => 'donation-campaigns',
            ],
        ];

        foreach ($models as $name => $config) {
            $this->info("Processing {$name}s...");
            $items = $config['query']->get();
            
            if ($items->isEmpty()) {
                $this->line("  No items found with legacy paths for {$name}.");
                continue;
            }

            foreach ($items as $item) {
                // Ensure we handle both potential fields (hero_image_path vs image_path)
                foreach ($config['fields'] as $field) {
                    $legacyPath = $item->{$field};
                    if (!$legacyPath) continue;

                    // Support both prefixed and non-prefixed paths (seeded often differ)
                    $cleanPath = ltrim($legacyPath, '/');
                    $possiblePaths = [
                        public_path('storage/' . $cleanPath),
                        storage_path('app/public/' . $cleanPath),
                    ];

                    $absolutePath = null;
                    foreach ($possiblePaths as $path) {
                        if (file_exists($path)) {
                            $absolutePath = $path;
                            break;
                        }
                    }

                    if (!$absolutePath) {
                        $this->warn("  File not found for {$name} ID {$item->id}: {$legacyPath}");
                        continue;
                    }

                    try {
                        // Create subfolder for the slug
                        $folderName = $item->slug ?? $item->title ?? 'unnamed';
                        $rootFolder = $mediaService->getOrCreateRootFolder($admin, $config['root']);
                        
                        $subFolder = Folder::firstOrCreate([
                            'user_id' => $admin->id,
                            'parent_id' => $rootFolder->id,
                            'name' => $folderName,
                            'is_system' => true,
                        ]);

                        $this->line("  Migrating: {$folderName} -> " . basename($legacyPath));

                        // Register file in CAS
                        $media = $mediaService->registerFile(
                            $admin,
                            $absolutePath,
                            basename($legacyPath),
                            [
                                'folder_id' => $subFolder->id, 
                                'visibility' => 'public'
                            ]
                        );

                        // Attach to model with 'featured' role
                        $item->media()->syncWithoutDetaching([$media->id => ['role' => 'featured']]);
                        
                        $this->info("    ✓ Registered in CAS: {$media->file_path}");

                        if ($this->option('cleanup')) {
                            $item->update([$field => null]);
                        }

                    } catch (\Exception $e) {
                        $this->error("    ✗ Failed to migrate {$name} ID {$item->id}: " . $e->getMessage());
                    }
                }
            }
        }

        $this->info('Migration complete!');
        return 0;
    }
}
