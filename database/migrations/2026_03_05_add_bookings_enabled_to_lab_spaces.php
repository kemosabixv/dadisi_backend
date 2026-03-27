<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PHASE 1: Staff Dashboard Lab Management
     * 
     * Adds `bookings_enabled` column to lab_spaces table to support disable/enable bookings feature.
     * - When FALSE: Prevents new member bookings from being created
     * - When TRUE: Lab accepts new bookings (normal operation)
     * - Existing bookings remain unaffected when disabled
     * - Lab operations (maintenance, attendance, assignments) remain operational
     * - Guest cancellation links remain functional regardless of this flag
     * - Default TRUE to preserve backward compatibility (all existing labs accept bookings)
     */
    public function up(): void
    {
        Schema::table('lab_spaces', function (Blueprint $table) {
            $table->boolean('bookings_enabled')
                ->default(true)
                ->after('name')
                ->comment('Whether this lab accepts new member bookings (independent of maintenance status)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_spaces', function (Blueprint $table) {
            $table->dropColumn('bookings_enabled');
        });
    }
};
