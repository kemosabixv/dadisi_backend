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
        Schema::table('lab_spaces', function (Blueprint $table) {
            // Daily opening time
            $table->time('opens_at')->nullable()->comment('Daily opening time');
            
            // Daily closing time
            $table->time('closes_at')->nullable()->comment('Daily closing time');
            
            // Operating days as JSON array (e.g., ["monday", "tuesday", "wednesday", ...])
            $table->json('operating_days')->nullable()->comment('Days of week the lab is open');
            
            // Maximum concurrent users per hour slot
            $table->integer('slots_per_hour')->default(1)->comment('Concurrent user capacity per hour');
            
            // Add indexes for queries filtering by operating hours
            $table->index('opens_at');
            $table->index('closes_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_spaces', function (Blueprint $table) {
            $table->dropIndex(['opens_at']);
            $table->dropIndex(['closes_at']);
            $table->dropColumn(['opens_at', 'closes_at', 'operating_days', 'slots_per_hour']);
        });
    }
};
