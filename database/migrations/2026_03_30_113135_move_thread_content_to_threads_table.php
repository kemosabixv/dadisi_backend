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
        // We use a subquery that handles the case where multiple first posts might be found (shouldn't happen)
        DB::statement("
            UPDATE forum_threads t
            JOIN (
                SELECT thread_id, content, id as post_id
                FROM (
                    SELECT thread_id, content, id,
                    ROW_NUMBER() OVER(PARTITION BY thread_id ORDER BY id ASC) as rn
                    FROM forum_posts
                ) p WHERE rn = 1
            ) fp ON t.id = fp.thread_id
            SET t.content = fp.content
            WHERE t.content IS NULL
        ");

        // Ensure no NULLs before modifying column (for zombie threads)
        DB::table('forum_threads')->whereNull('content')->update(['content' => '(No description)']);

        // Cleanup: Delete the first-posts as they are now redundant
        DB::statement("
            DELETE FROM forum_posts 
            WHERE id IN (
                SELECT post_id FROM (
                    SELECT id as post_id,
                    ROW_NUMBER() OVER(PARTITION BY thread_id ORDER BY id ASC) as rn
                    FROM forum_posts
                ) p WHERE rn = 1
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
