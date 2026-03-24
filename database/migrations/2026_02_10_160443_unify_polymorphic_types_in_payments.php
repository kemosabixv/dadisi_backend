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
        // Migrate Donation types
        DB::table('payments')
            ->where('payable_type', 'App\Models\Donation')
            ->orWhere('payable_type', 'donation')
            ->update(['payable_type' => 'donation']);

        // Migrate EventOrder types
        DB::table('payments')
            ->where('payable_type', 'App\Models\EventOrder')
            ->orWhere('payable_type', 'event_order')
            ->update(['payable_type' => 'event_order']);

        // Migrate PlanSubscription types
        DB::table('payments')
            ->where('payable_type', 'App\Models\PlanSubscription')
            ->orWhere('payable_type', 'subscription')
            ->update(['payable_type' => 'subscription']);
            
        // Migrate Model names to short names in metadata if any (just in case)
        DB::table('payments')
            ->where('meta', 'like', '%App\\\\Models\\\\Donation%')
            ->update([
                'meta' => DB::raw("REPLACE(meta, 'App\\\\Models\\\\Donation', 'donation')")
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert Donation types
        DB::table('payments')
            ->where('payable_type', 'donation')
            ->update(['payable_type' => 'App\Models\Donation']);

        // Revert EventOrder types
        DB::table('payments')
            ->where('payable_type', 'event_order')
            ->update(['payable_type' => 'App\Models\EventOrder']);

        // Revert PlanSubscription types
        DB::table('payments')
            ->where('payable_type', 'subscription')
            ->update(['payable_type' => 'App\Models\PlanSubscription']);
    }
};
