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
        Schema::create('subscription_enhancements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            // Note: Foreign key constraint removed due to compatibility issues with polymorphic subscriber relationship
            // Data integrity is maintained via PlanSubscriptionObserver which cascades deletes at application level
            // See: app/Observers/PlanSubscriptionObserver.php
            $table->enum('status', ['active', 'payment_pending', 'payment_failed', 'grace_period', 'suspended', 'cancelled'])->default('active');
            $table->enum('payment_failure_state', ['retry_immediate', 'retry_delayed', 'manual_intervention', 'abandoned'])->nullable();
            $table->integer('renewal_attempts')->default(0);
            $table->integer('max_renewal_attempts')->default(3);
            $table->timestamp('last_renewal_attempt_at')->nullable();
            $table->timestamp('grace_period_started_at')->nullable();
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('payment_failure_state');
            $table->index('next_retry_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_enhancements');
    }
};
