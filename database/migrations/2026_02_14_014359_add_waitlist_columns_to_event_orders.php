<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_orders', function (Blueprint $table) {
            $table->integer('waitlist_position')->nullable()->after('status');
            // If the status is an enum, we might need a raw query to update it 
            // but usually in Laravel 10+ we can just modify it if the driver supports it.
            // However, to be safe, let's just make sure we handle the status.
        });

        // Add 'waitlisted' to status if it's an enum (MySQL specific)
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE event_orders MODIFY COLUMN status ENUM('pending', 'paid', 'cancelled', 'failed', 'refunded', 'waitlisted') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_orders', function (Blueprint $table) {
            $table->dropColumn('waitlist_position');
        });
        
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE event_orders MODIFY COLUMN status ENUM('pending', 'paid', 'cancelled', 'failed', 'refunded') DEFAULT 'pending'");
        }
    }
};
