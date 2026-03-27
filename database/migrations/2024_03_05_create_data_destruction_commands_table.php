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
        if (!Schema::hasTable('data_destruction_commands')) {
            Schema::create('data_destruction_commands', function (Blueprint $table) {
                $table->id();
                
                // Command identification
                $table->string('command_name')->unique()->index();
                $table->string('job_class')->nullable()->comment('Associated Job class for async execution');
                
                // Data classification
                $table->enum('data_type', [
                    'audit_logs',
                    'webhook_events',
                    'pending_payments',
                    'failed_jobs',
                    'user_sessions',
                    'temporary_media',
                    'user_data',
                    'generic',
                ])->index();
                
                // Documentation
                $table->text('description');
                $table->text('notes')->nullable();
                
                // Execution configuration
                $table->enum('frequency', [
                    'everyFifteenMinutes',
                    'everyThirtyMinutes',
                    'hourly',
                    'everyFourHours',
                    'daily',
                    'weekly',
                    'monthly',
                    'manual_only',
                ])->default('daily')->index();
                
                // Feature flags
                $table->boolean('is_enabled')->default(true)->index();
                $table->boolean('supports_dry_run')->default(true);
                $table->boolean('supports_sync')->default(true);
                $table->boolean('is_critical')->default(false)->comment('Alert if fails');
                
                // Execution tracking
                $table->integer('affected_records_count')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->json('metadata')->nullable()->comment('Last run results and statistics');
                
                // Timestamps
                $table->timestamps();
                
                // Indexes for common queries - only create once
                $table->index(['is_enabled', 'frequency']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_destruction_commands');
    }
};
