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
            $table->boolean('pesapal_recurring_enabled')->default(false)->after('renewal_mode');
            $table->string('pesapal_account_reference')->nullable()->after('pesapal_recurring_enabled');
            $table->string('pesapal_subscription_frequency')->nullable()->after('pesapal_account_reference');
            $table->timestamp('last_pesapal_recurring_at')->nullable()->after('pesapal_subscription_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_enhancements', function (Blueprint $table) {
            $table->dropColumn([
                'pesapal_recurring_enabled',
                'pesapal_account_reference',
                'pesapal_subscription_frequency',
                'last_pesapal_recurring_at',
            ]);
        });
    }
};
