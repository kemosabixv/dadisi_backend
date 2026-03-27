<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update primary public folders
        $primaryPublic = ['Public', 'Blog', 'Events', 'Donations', 'Lab Spaces'];
        
        DB::table('folders')->whereIn('name', $primaryPublic)->update([
            'root_type' => 'public',
            'is_system' => true
        ]);

        // 2. Cascade root_type to subfolders (depth 3)
        for ($i = 0; $i < 3; $i++) {
            DB::table('folders')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('folders as parent')
                        ->whereColumn('parent.id', 'folders.parent_id')
                        ->where('parent.root_type', 'public');
                })
                ->update(['root_type' => 'public']);
        }

        // 3. Update media content based on folder root
        DB::table('media')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('folders')
                    ->whereColumn('folders.id', 'media.folder_id')
                    ->where('folders.root_type', 'public');
            })
            ->update([
                'root_type' => 'public',
                'visibility' => 'public'
            ]);

        // 4. Update orphan public media
        DB::table('media')
            ->whereNull('folder_id')
            ->where('visibility', 'public')
            ->update(['root_type' => 'public']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
