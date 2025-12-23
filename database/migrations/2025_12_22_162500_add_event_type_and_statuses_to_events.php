<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add event_type column
        Schema::table('events', function (Blueprint $table) {
            $table->enum('event_type', ['organization', 'user'])
                ->default('user')
                ->after('status')
                ->comment('organization = Dadisi events, user = community events');
        });

        // 2. Expand status enum - MySQL requires recreating the column
        // First, change to string temporarily to preserve data
        DB::statement("ALTER TABLE events MODIFY COLUMN status VARCHAR(50) DEFAULT 'draft'");

        // Now change back to enum with expanded values
        DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('draft', 'pending_approval', 'published', 'rejected', 'cancelled', 'suspended') DEFAULT 'draft'");
    }

    public function down(): void
    {
        // Revert status to original enum
        DB::statement("ALTER TABLE events MODIFY COLUMN status VARCHAR(50) DEFAULT 'draft'");
        DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('draft', 'published') DEFAULT 'draft'");

        // Remove event_type column
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('event_type');
        });
    }
};
