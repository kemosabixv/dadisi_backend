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
        Schema::table('donation_campaigns', function (Blueprint $table) {
            $table->decimal('current_amount', 15, 2)->default(0)->after('currency');
            $table->integer('donor_count')->default(0)->after('current_amount');
        });

        // Initialize values
        $campaigns = \App\Models\DonationCampaign::all();
        foreach ($campaigns as $campaign) {
            $stats = $campaign->donations()->where('status', 'paid')->selectRaw('COUNT(*) as donor_count, SUM(amount) as current_amount')->first();
            $campaign->update([
                'current_amount' => $stats->current_amount ?? 0,
                'donor_count' => $stats->donor_count ?? 0,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donation_campaigns', function (Blueprint $table) {
            $table->dropColumn(['current_amount', 'donor_count']);
        });
    }
};
