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
        // 1. Create Physical Blobs (CAS) table
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('hash')->unique()->index();
            $table->string('disk')->default('r2');
            $table->string('path')->unique();
            $table->unsignedBigInteger('size');
            $table->string('mime_type');
            $table->unsignedInteger('ref_count')->default(0);
            $table->timestamps();
        });

        // 2. Create Virtual Folders table
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('folders')->onDelete('cascade');
            $table->string('name');
            $table->string('root_type')->default('personal'); // personal, public
            $table->boolean('is_system')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            // Unique key per parent/user scope
            $table->unique(['user_id', 'parent_id', 'name']);
        });

        // Drop legacy indices before columns (required for SQLite)
        // We use separate try-catch blocks per index drop to avoid failing if one doesn't exist
        try {
            Schema::table('media', function (Blueprint $table) {
                $table->dropIndex(['owner_type', 'owner_id']);
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('media', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'is_public']);
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('media', function (Blueprint $table) {
                $table->dropIndex(['attached_to_id']);
            });
        } catch (\Exception $e) {
        }

        // 3. Update Virtual Media table
        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('media_file_id')->nullable()->after('user_id')->constrained('media_files')->onDelete('set null');
            $table->foreignId('folder_id')->nullable()->after('media_file_id')->constrained('folders')->onDelete('set null');
            $table->unsignedInteger('usage_count')->default(0)->after('file_size');

            // Drop legacy columns
            $table->dropColumn(['owner_type', 'owner_id', 'disk', 'file_path', 'is_public', 'attached_to', 'attached_to_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropForeign(['media_file_id']);
            $table->dropColumn('media_file_id');
            $table->dropForeign(['folder_id']);
            $table->dropColumn('folder_id');
            $table->dropColumn('usage_count');
        });

        Schema::dropIfExists('folders');
        Schema::dropIfExists('media_files');
    }
};
