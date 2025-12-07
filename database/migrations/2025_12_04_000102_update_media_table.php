<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Update existing media table or create if doesn't exist
        if (!Schema::hasTable('media')) {
            Schema::create('media', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('file_name');
                $table->string('file_path');
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('file_size');
                $table->enum('type', ['image', 'audio', 'video', 'pdf']);
                $table->boolean('is_public')->default(false);
                $table->string('attached_to')->nullable(); // 'blog_post', etc.
                $table->unsignedBigInteger('attached_to_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'is_public']);
                $table->index(['type']);
                $table->index(['attached_to_id']);
            });
        } else {
            // Update existing media table structure safely (do not rely on `after()` ordering)
            Schema::table('media', function (Blueprint $table) {
                // Core file columns
                if (!Schema::hasColumn('media', 'file_name')) {
                    $table->string('file_name');
                }
                if (!Schema::hasColumn('media', 'file_path')) {
                    $table->string('file_path');
                }
                if (!Schema::hasColumn('media', 'mime_type')) {
                    $table->string('mime_type', 100)->nullable();
                }
                if (!Schema::hasColumn('media', 'file_size')) {
                    $table->unsignedBigInteger('file_size')->nullable();
                }

                // Type / privacy / attachments
                if (!Schema::hasColumn('media', 'type')) {
                    $table->enum('type', ['image', 'audio', 'video', 'pdf'])->nullable();
                }
                if (!Schema::hasColumn('media', 'is_public')) {
                    $table->boolean('is_public')->default(false);
                }
                if (!Schema::hasColumn('media', 'attached_to')) {
                    $table->string('attached_to')->nullable();
                }
                if (!Schema::hasColumn('media', 'attached_to_id')) {
                    $table->unsignedBigInteger('attached_to_id')->nullable();
                }

                // Soft deletes & timestamps
                if (!Schema::hasColumn('media', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('media', 'created_at')) {
                    $table->timestamps();
                }

                // Indexes (guard with hasColumn checks)
                try {
                    if (Schema::hasColumn('media', 'user_id') && Schema::hasColumn('media', 'is_public')) {
                        $table->index(['user_id', 'is_public']);
                    }
                    if (Schema::hasColumn('media', 'type')) {
                        $table->index(['type']);
                    }
                    if (Schema::hasColumn('media', 'attached_to_id')) {
                        $table->index(['attached_to_id']);
                    }
                } catch (\Exception $e) {
                    // Index creation can fail on some DB states; ignore and continue
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
