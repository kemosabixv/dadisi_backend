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
        // 1. Expand the ENUM to include 'confirmed'
        Schema::table('lab_bookings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'confirmed', 'rejected', 'cancelled', 'completed', 'no_show'])->default('pending')->change();
        });

        // 2. Update all 'approved' statuses to 'confirmed'
        DB::table('lab_bookings')
            ->where('status', 'approved')
            ->update(['status' => 'confirmed']);

        // 3. Narrow the ENUM to remove 'approved'
        Schema::table('lab_bookings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'rejected', 'cancelled', 'completed', 'no_show'])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Expand the ENUM to include 'approved'
        Schema::table('lab_bookings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'confirmed', 'rejected', 'cancelled', 'completed', 'no_show'])->default('pending')->change();
        });

        // 2. Revert 'confirmed' statuses back to 'approved'
        DB::table('lab_bookings')
            ->where('status', 'confirmed')
            ->update(['status' => 'approved']);

        // 3. Narrow the ENUM to remove 'confirmed'
        Schema::table('lab_bookings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'completed', 'no_show'])->default('pending')->change();
        });
    }
};
