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
        Schema::table(config('laravel-subscriptions.tables.subscriptions'), function (Blueprint $table): void {
            // Track when the subscription is scheduled to be cancelled (at end of billing cycle)
            $table->timestamp('pending_cancellation_at')->nullable()->after('canceled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('laravel-subscriptions.tables.subscriptions'), function (Blueprint $table): void {
            $table->dropColumn('pending_cancellation_at');
        });
    }
};
