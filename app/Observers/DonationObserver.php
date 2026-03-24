<?php

namespace App\Observers;

use App\Models\Donation;
use Illuminate\Support\Facades\Log;

class DonationObserver
{
    /**
     * Handle the Donation "saved" event.
     * 
     * Handles both creation and updates, specifically checking for status transitions.
     */
    public function saved(Donation $donation): void
    {
        if ($donation->campaign_id) {
            $this->updateCampaignStats($donation);
        }
    }

    /**
     * Handle the Donation "deleted" event.
     */
    public function deleted(Donation $donation): void
    {
        if ($donation->campaign_id && $donation->status === 'paid') {
            $this->updateCampaignStats($donation);
        }
    }

    /**
     * Update the associated campaign statistics.
     */
    protected function updateCampaignStats(Donation $donation): void
    {
        $campaign = $donation->campaign;
        
        if (!$campaign) {
            return;
        }

        try {
            $stats = $campaign->donations()
                ->where('status', 'paid')
                ->selectRaw('COUNT(*) as donor_count, SUM(amount) as current_amount')
                ->first();

            $campaign->update([
                'current_amount' => $stats->current_amount ?? 0,
                'donor_count' => $stats->donor_count ?? 0,
            ]);

            Log::debug('Campaign stats updated via Observer', [
                'campaign_id' => $campaign->id,
                'current_amount' => $campaign->current_amount,
                'donor_count' => $campaign->donor_count,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update campaign stats in observer', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
