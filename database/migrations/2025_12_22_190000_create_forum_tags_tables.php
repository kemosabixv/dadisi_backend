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
        // Forum Tags table
        Schema::create('forum_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 7)->nullable()->default('#6366f1'); // Hex color
            $table->text('description')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index('usage_count');
        });

        // Pivot table for forum thread tags
        Schema::create('forum_thread_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('forum_threads')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('forum_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['thread_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forum_thread_tag');
        Schema::dropIfExists('forum_tags');
    }
};
