<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Enhance maintenance_block_rollovers table
        Schema::table('maintenance_block_rollovers', function (Blueprint $table) {
            $table->json('original_booking_data')->after('rolled_over_booking_id')->nullable();
            $table->text('notes')->after('rejection_reason')->nullable();
        });

        if (config('database.default') === 'mysql') {
            // 2. Add status to lab_bookings
            DB::statement("ALTER TABLE lab_bookings MODIFY COLUMN status ENUM('pending', 'confirmed', 'rejected', 'cancelled', 'completed', 'no_show', 'pending_user_resolution') NOT NULL DEFAULT 'pending'");

            // 3. Update maintenance_block_rollovers status enum
            DB::statement("ALTER TABLE maintenance_block_rollovers MODIFY COLUMN status ENUM('initiated', 'pending_user', 'escalated', 'rolled_over', 'cancelled') NOT NULL DEFAULT 'initiated'");
        } else {
            // For SQLite (tests), we can't easily modify ENUM constraints without recreating the table.
            // We'll change them to string for testing purposes.
            Schema::table('lab_bookings', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
            });
            Schema::table('maintenance_block_rollovers', function (Blueprint $table) {
                $table->string('status')->default('initiated')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_block_rollovers', function (Blueprint $table) {
            $table->dropColumn(['original_booking_data', 'notes']);
        });

        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE lab_bookings MODIFY COLUMN status ENUM('pending', 'confirmed', 'rejected', 'cancelled', 'completed', 'no_show') NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE maintenance_block_rollovers MODIFY COLUMN status ENUM('pending', 'rolled_over', 'cancelled') NOT NULL DEFAULT 'pending'");
        }
    }
};
