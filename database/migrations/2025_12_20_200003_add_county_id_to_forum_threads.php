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
        Schema::table('forum_threads', function (Blueprint $table) {
            $table->foreignId('county_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
            
            $table->index('county_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forum_threads', function (Blueprint $table) {
            if (Schema::hasColumn('forum_threads', 'county_id')) {
                // Drop foreign key safely
                if (Schema::hasIndex('forum_threads', 'forum_threads_county_id_foreign')) {
                    $table->dropForeign('forum_threads_county_id_foreign');
                }
                
                // Drop index safely
                if (Schema::hasIndex('forum_threads', 'forum_threads_county_id_index')) {
                    $table->dropIndex('forum_threads_county_id_index');
                }
                
                $table->dropColumn('county_id');
            }
        });
    }
};
