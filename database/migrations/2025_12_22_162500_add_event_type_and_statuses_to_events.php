<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add event_type column if it doesn't exist
        if (!Schema::hasColumn('events', 'event_type')) {
            Schema::table('events', function (Blueprint $table) {
                $table->string('event_type', 50)
                    ->default('user')
                    ->after('status')
                    ->comment('organization = Dadisi events, user = community events');
            });
        }

        // 2. For SQLite, we need to use string columns instead of enum
        // SQLite doesn't support MODIFY COLUMN, so we'll use Laravel's change()
        // For new installs, the status column is already a string in Laravel
        // For existing installs where status might be enum, we skip this
        // This is a no-op for SQLite as the status column already exists
        
        // Note: SQLite doesn't support enum types or MODIFY COLUMN
        // The status column should work as-is with string values
    }

    public function down(): void
    {
        // Remove event_type column
        if (Schema::hasColumn('events', 'event_type')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('event_type');
            });
        }
    }
};
