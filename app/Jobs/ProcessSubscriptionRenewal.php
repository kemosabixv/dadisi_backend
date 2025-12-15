<?php

namespace App\Jobs;

use App\Models\PlanSubscription;
use App\Services\AutoRenewalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSubscriptionRenewal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $subscriptionId;

    public function __construct(int $subscriptionId)
    {
        $this->subscriptionId = $subscriptionId;
    }

    public function handle(AutoRenewalService $service)
    {
        $subscription = PlanSubscription::find($this->subscriptionId);
        if (!$subscription) {
            return;
        }

        $service->processSubscriptionRenewal($subscription);
    }
}
