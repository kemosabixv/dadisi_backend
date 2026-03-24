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
        DB::table('system_settings')->insert([
            [
                'key' => 'subscription_refund_threshold_monthly_days',
                'value' => '14',
                'group' => 'general',
                'type' => 'integer',
                'description' => 'Maximum days after payment for monthly subscription refund eligibility',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'subscription_refund_threshold_yearly_days',
                'value' => '90',
                'group' => 'general',
                'type' => 'integer',
                'description' => 'Maximum days after payment for yearly subscription refund eligibility',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_settings')
            ->whereIn('key', [
                'subscription_refund_threshold_monthly_days',
                'subscription_refund_threshold_yearly_days',
            ])
            ->delete();
    }
};
