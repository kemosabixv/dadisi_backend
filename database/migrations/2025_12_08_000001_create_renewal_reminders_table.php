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
        Schema::create('renewal_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('reminder_type', ['seven_days', 'three_days', 'one_day', 'final_notice'])->index();
            $table->integer('days_before_expiry')->comment('Number of days before expiry this reminder is for');
            $table->timestamp('scheduled_at')->index()->comment('When this reminder should be sent');
            $table->timestamp('sent_at')->nullable()->index()->comment('When reminder was actually sent');
            $table->boolean('is_sent')->default(false)->index();
            $table->string('channel')->default('email')->comment('email, sms, push, etc.');
            $table->json('metadata')->nullable()->comment('Additional data like email address, phone, etc.');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient querying
            // Use the subscriptions table name from the subscriptions config to avoid
            // hard-coding a table name that may differ across installs.
            $table->foreign('subscription_id')
                ->references('id')
                ->on(config('laravel-subscriptions.tables.subscriptions'))
                ->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['subscription_id', 'reminder_type'], 'unique_subscription_reminder_type');
            $table->index(['scheduled_at', 'is_sent', 'reminder_type'], 'idx_pending_reminders');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renewal_reminders');
    }
};
