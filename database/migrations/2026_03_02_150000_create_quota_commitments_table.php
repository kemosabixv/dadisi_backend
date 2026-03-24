<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PHASE 2: Monthly Quota Replenishment & Recurring Commitments
     * 
     * Tracks monthly lab quota commitments per user.
     * - Each user gets one quota_commitment record per month
     * - Quota comes from their active subscription plan's lab_hours_monthly feature
     * - Unused monthly quota expires at month end (zero carryover to next month)
     * - Quota is consumed (deducted) when bookings are confirmed
     * - Recurring bookings can commit quota across future months
     * 
     * Design Notes:
     * - Per-user tracking (not per-subscription): Users have 1 active subscription at a time
     * - Month-based key: Simple monthly model vs quarterly complexity
     * - Threshold warning: Track 80% warning to avoid repeated notifications
     * - Replenished_at: Null until auto-replenished by scheduler
     */
    public function up(): void
    {
        Schema::dropIfExists('quota_commitments');  // Ensure clean state

        Schema::create('quota_commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Month for which this quota applies (always first day of month)
            $table->date('month_date')->comment('First day of month (e.g., 2026-03-01)');
            
            // Quota hours for this month (from plan's lab_hours_monthly feature)
            $table->integer('committed_hours')->comment('Monthly quota hours from subscription plan');
            
            // Hours consumed by confirmed bookings this month
            $table->decimal('used_hours', 8, 2)->default(0)->comment('Hours consumed by confirmed bookings');
            
            // Percentage threshold to trigger warning notification
            $table->integer('warning_threshold_percent')->default(80)->comment('Trigger warning at this percentage (default 80%)');
            
            // Whether user has been warned at threshold for this month
            $table->boolean('warned_at_threshold')->default(false)->comment('User has been notified at 80% threshold');
            
            // When quota was auto-replenished (null = not yet replenished)
            $table->timestamp('replenished_at')->nullable()->comment('Timestamp when quota auto-replenished by scheduler');
            
            $table->timestamps();
            
            // Constraints
            $table->unique(['user_id', 'month_date'], 'unique_user_month_quota');
            $table->index(['month_date', 'warned_at_threshold'], 'idx_month_warning');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quota_commitments');
    }
};
