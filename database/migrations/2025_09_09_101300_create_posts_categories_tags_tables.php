<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->enum('status', ['draft','published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('hero_image_path', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->timestamps();
        });

        Schema::create('post_category', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->primary(['post_id','category_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->timestamps();
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['post_id','tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('post_category');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('posts');
    }
};

