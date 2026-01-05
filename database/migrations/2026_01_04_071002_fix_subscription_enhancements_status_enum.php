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
        Schema::table('subscription_enhancements', function (Blueprint $table) {
            // Change status from enum to string to support more statuses like pending_approval
            $table->string('status', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_enhancements', function (Blueprint $table) {
            $table->enum('status', ['active', 'payment_pending', 'payment_failed', 'grace_period', 'suspended', 'cancelled'])->default('active')->change();
        });
    }
};
