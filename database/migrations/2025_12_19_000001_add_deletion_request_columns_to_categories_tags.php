<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add requested_deletion_at to categories
        Schema::table('categories', function (Blueprint $table) {
            $table->timestamp('requested_deletion_at')->nullable()->after('updated_at');
            $table->foreignId('deletion_requested_by')
                ->nullable()
                ->after('requested_deletion_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Add created_by, description, and requested_deletion_at to tags
        Schema::table('tags', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('description', 255)->nullable()->after('slug');

            $table->timestamp('requested_deletion_at')->nullable()->after('updated_at');
            $table->foreignId('deletion_requested_by')
                ->nullable()
                ->after('requested_deletion_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deletion_requested_by');
            $table->dropColumn('requested_deletion_at');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deletion_requested_by');
            $table->dropColumn('requested_deletion_at');
            $table->dropColumn('description');
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
