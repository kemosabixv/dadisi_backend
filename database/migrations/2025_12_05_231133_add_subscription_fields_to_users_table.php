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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('active_subscription_id')->nullable()->constrained('plan_subscriptions');
            $table->foreignId('plan_id')->nullable()->constrained('plans');
            $table->enum('subscription_status', ['active', 'payment_pending', 'payment_failed', 'grace_period', 'suspended', 'cancelled'])->default('active');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->timestamp('last_payment_date')->nullable();
            $table->timestamp('subscription_activated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_subscription_id');
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('subscription_status');
            $table->dropColumn('subscription_expires_at');
            $table->dropColumn('last_payment_date');
            $table->dropColumn('subscription_activated_at');
        });
    }
};
