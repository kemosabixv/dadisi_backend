<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PlanSubscription;
use App\Jobs\ProcessSubscriptionRenewal;
use Illuminate\Support\Facades\Log;

class EnqueueDueRenewals extends Command
{
    protected $signature = 'renewals:enqueue-due';

    protected $description = 'Enqueue due automatic renewals for subscriptions with next_auto_renewal_at <= now and renewal_mode=automatic';

    public function handle()
    {
        $this->info('Scanning for due automatic renewals...');

        $subscriptions = PlanSubscription::whereHas('enhancement', function ($q) {
            $q->where('renewal_mode', 'automatic')
              ->whereNotNull('next_auto_renewal_at')
              ->where('next_auto_renewal_at', '<=', now());
        })->get();

        $this->info('Found ' . $subscriptions->count() . ' subscriptions due for auto-renewal');

        foreach ($subscriptions as $subscription) {
            try {
                ProcessSubscriptionRenewal::dispatch($subscription->id);
                $this->info('Enqueued renewal for subscription ' . $subscription->id);
            } catch (\Exception $e) {
                Log::error('Failed to enqueue renewal', ['subscription_id' => $subscription->id, 'error' => $e->getMessage()]);
            }
        }

        return 0;
    }
}
