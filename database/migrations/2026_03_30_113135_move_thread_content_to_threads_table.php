<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('forum_threads', 'content')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                $table->longText('content')->nullable()->after('slug');
            });
        }

        // Migrate data: Copy the content of the first post into the thread content column
        // Database agnostic approach (works in MySQL and SQLite)
        DB::statement("
            UPDATE forum_threads 
            SET content = (
                SELECT content 
                FROM forum_posts 
                WHERE forum_posts.thread_id = forum_threads.id 
                ORDER BY id ASC 
                LIMIT 1
            )
            WHERE content IS NULL
        ");

        // Ensure no NULLs before modifying column (for zombie threads)
        DB::table('forum_threads')->whereNull('content')->update(['content' => '(No description)']);

        // Cleanup: Delete the first-posts as they are now redundant
        // Database agnostic approach (works in MySQL and SQLite)
        DB::statement("
            DELETE FROM forum_posts 
            WHERE id IN (
                SELECT id FROM (
                    SELECT MIN(id) as id
                    FROM forum_posts
                    GROUP BY thread_id
                ) as ids_to_delete
            )
        ");

        // Finalize: Make content required
        Schema::table('forum_threads', function (Blueprint $table) {
            $table->longText('content')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversing is complex: we'd have to recreate posts. 
        // For simplicity, we just drop the column and lose that data (or prevent reversal)
        Schema::table('forum_threads', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
