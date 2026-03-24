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
        // Flatten the hierarchy: Move all contents of 'Public' and 'Personal' virtual-root folders 
        // to the true virtual root (null parent) and delete the placeholders.
        
        $redundantFolders = DB::table('folders')
            ->whereNull('parent_id')
            ->whereIn('name', ['Public', 'Personal'])
            ->get();

        foreach ($redundantFolders as $folder) {
            // 1. Move subfolders up to true root
            DB::table('folders')
                ->where('parent_id', $folder->id)
                ->update(['parent_id' => null]);

            // 2. Move media up to true root
            DB::table('media')
                ->where('folder_id', $folder->id)
                ->update(['folder_id' => null]);

            // 3. Delete the redundant folder record
            DB::table('folders')->where('id', $folder->id)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
