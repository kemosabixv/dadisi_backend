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
        Schema::create('auto_renewal_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('user_id');
            // Include 'cancelled' as a valid status to match controller actions
            $table->enum('status', ['pending', 'processing', 'succeeded', 'failed', 'retry_scheduled', 'cancelled'])->default('pending')->index();
            $table->enum('attempt_type', ['initial', 'retry_24h', 'retry_3d', 'retry_7d', 'manual_retry'])->default('initial');
            $table->integer('attempt_number')->default(1);
            $table->integer('max_attempts')->default(3);
            $table->timestamp('scheduled_at')->index();
            $table->timestamp('executed_at')->nullable();
            $table->string('payment_method')->nullable()->comment('Which payment method was attempted');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency')->default('KES');
            $table->string('transaction_id')->nullable()->index();
            $table->string('payment_gateway_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->json('metadata')->nullable()->comment('Additional context data');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->foreign('subscription_id')->references('id')->on(config('laravel-subscriptions.tables.subscriptions'))->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['status', 'scheduled_at'], 'idx_pending_renewals');
            $table->index(['user_id', 'status'], 'idx_user_renewal_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_renewal_jobs');
    }
};
