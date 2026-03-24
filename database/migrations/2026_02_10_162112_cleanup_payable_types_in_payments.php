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
        // 1. Cleanup payments table (payable_type)
        DB::table('payments')
            ->where('payable_type', 'App\Models\Donation')
            ->update(['payable_type' => 'donation']);

        DB::table('payments')
            ->where('payable_type', 'App\Models\EventOrder')
            ->update(['payable_type' => 'event_order']);

        DB::table('payments')
            ->where('payable_type', 'App\Models\PlanSubscription')
            ->update(['payable_type' => 'subscription']);

        // 2. Cleanup plan_subscriptions table (subscriber_type)
        if (Schema::hasTable('plan_subscriptions')) {
            DB::table('plan_subscriptions')
                ->where('subscriber_type', 'App\Models\User')
                ->update(['subscriber_type' => 'user']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse payments table cleanup
        DB::table('payments')
            ->where('payable_type', 'donation')
            ->update(['payable_type' => 'App\Models\Donation']);

        DB::table('payments')
            ->where('payable_type', 'event_order')
            ->update(['payable_type' => 'App\Models\EventOrder']);

        DB::table('payments')
            ->where('payable_type', 'subscription')
            ->update(['payable_type' => 'App\Models\PlanSubscription']);

        // Reverse plan_subscriptions table cleanup
        if (Schema::hasTable('plan_subscriptions')) {
            DB::table('plan_subscriptions')
                ->where('subscriber_type', 'user')
                ->update(['subscriber_type' => 'App\Models\User']);
        }
    }
};
