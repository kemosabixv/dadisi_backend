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
        Schema::table('media', function (Blueprint $table) {
            if (!Schema::hasColumn('media', 'root_type')) {
                $table->enum('root_type', ['personal', 'public'])->default('personal')->after('folder_id');
            }
            if (!Schema::hasColumn('media', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('share_token');
            }
        });

        // Data migration: Set root_type based on folder or visibility
        DB::table('media')
            ->whereNull('folder_id')
            ->where('visibility', 'public')
            ->update(['root_type' => 'public']);

        DB::table('media')
            ->whereNotNull('folder_id')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('folders')
                    ->whereColumn('folders.id', 'media.folder_id')
                    ->where('folders.root_type', 'public');
            })
            ->update(['root_type' => 'public']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['root_type', 'expires_at']);
        });
    }
};
