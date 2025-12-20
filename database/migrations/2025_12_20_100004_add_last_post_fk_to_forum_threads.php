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
        // Add FK constraint after forum_posts table exists
        Schema::table('forum_threads', function (Blueprint $table) {
            $table->foreign('last_post_id')
                  ->references('id')
                  ->on('forum_posts')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forum_threads', function (Blueprint $table) {
            $table->dropForeign(['last_post_id']);
        });
    }
};
