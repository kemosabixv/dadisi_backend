<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('posts', 'county_id')) {
                $table->foreignId('county_id')->nullable()->after('user_id')->constrained('counties')->nullOnDelete();
            }

            if (!Schema::hasColumn('posts', 'meta_title')) {
                $table->string('meta_title', 60)->nullable()->after('hero_image_path');
            }

            if (!Schema::hasColumn('posts', 'meta_description')) {
                $table->string('meta_description', 160)->nullable()->after('meta_title');
            }

            if (!Schema::hasColumn('posts', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('meta_description');
            }

            if (!Schema::hasColumn('posts', 'views_count')) {
                $table->unsignedBigInteger('views_count')->default(0)->after('is_featured');
            }

            if (!Schema::hasColumn('posts', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }

            // Add an index for status + published_at
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // This is a non-destructive migration, so we'll leave the down minimal
        });
    }
};
