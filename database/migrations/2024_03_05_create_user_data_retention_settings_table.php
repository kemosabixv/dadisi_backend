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
        Schema::dropIfExists('user_data_retention_settings');

        Schema::create('user_data_retention_settings', function (Blueprint $table) {
            $table->id();
            
            // Data type being retained
            $table->enum('data_type', [
                'user_accounts',
                'audit_logs',
                'backups',
                'temp_files',
                'session_data',
                'webhook_events',
                'pending_payments',
                'failed_jobs',
                'user_sessions',
                'temporary_media',
                'orphaned_media',
                'other',
            ])->unique()->index();
            
            // Retention configuration
            $table->integer('retention_days')->default(90);
            $table->integer('retention_minutes')->nullable()->default(null);
            
            // Deletion strategy
            $table->boolean('auto_delete')->default(true)->index();
            $table->boolean('is_soft_delete')->default(true)->comment('If true, mark as deleted; if false, hard delete');
            
            // Admin control
            $table->boolean('is_enabled')->default(true)->index();
            
            // Documentation
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            
            // Audit trail
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Timestamps
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_data_retention_settings');
    }
};
