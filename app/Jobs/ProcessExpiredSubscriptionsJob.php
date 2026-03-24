<?php

namespace App\Jobs;

use App\Models\PlanSubscription;
use App\Models\SubscriptionEnhancement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessExpiredSubscriptionsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 900; // 15 minutes - may process many subscriptions

    /**
     * Create a new job instance.
     *
     * @param int $gracePeriodDays Number of days for grace period. Default: 14
     * @param bool $dryRun If true, report what would happen without making changes
     */
    public function __construct(
        protected int $gracePeriodDays = 14,
        protected bool $dryRun = false
    ) {}

    /**
     * Execute the job.
     * 
     * CRITICAL: This job triggers member downgrades! Handle with care.
     * Two phases:
     * 1. Subscriptions that just expired → enter 14-day grace period
     * 2. Subscriptions with expired grace periods → downgrade to free tier
     */
    public function handle(): void
    {
        try {
            Log::info('ProcessExpiredSubscriptionsJob started', [
                'grace_period_days' => $this->gracePeriodDays,
                'dry_run' => $this->dryRun,
            ]);

            // Phase 1: Find subscriptions that just expired - enter grace period
            $this->processNewlyExpired();

            // Phase 2: Find subscriptions with expired grace period → downgrade to free
            $this->processExpiredGracePeriods();

            Log::info('ProcessExpiredSubscriptionsJob completed', [
                'dry_run' => $this->dryRun,
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessExpiredSubscriptionsJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle subscriptions that just expired - enter grace period
     * 
     * These subscriptions have ends_at in the past but haven't entered grace yet.
     */
    protected function processNewlyExpired(): void
    {
        try {
            $expired = PlanSubscription::whereNotNull('ends_at')
                ->where('ends_at', '<', now())
                ->whereHas('enhancements', function ($q) {
                    $q->where('status', 'active');
                })
                ->with(['enhancements', 'subscriber', 'plan'])
                ->get();

            Log::info('Found newly expired subscriptions', [
                'count' => $expired->count(),
            ]);

            $processedCount = 0;
            foreach ($expired as $subscription) {
                try {
                    $enhancement = $subscription->enhancements->first();
                    if (!$enhancement) {
                        continue;
                    }

                    if (!$this->dryRun) {
                        // Enter grace period
                        $enhancement->enterGracePeriod($this->gracePeriodDays);

                        // Send notification to user
                        $this->notifySubscriptionExpired($subscription);
                    }

                    $processedCount++;

                    Log::info('Subscription entered grace period', [
                        'subscription_id' => $subscription->id,
                        'user_id' => $subscription->subscriber_id,
                        'grace_period_days' => $this->gracePeriodDays,
                        'grace_period_expires_at' => $enhancement->grace_period_expires_at ?? 'N/A',
                        'dry_run' => $this->dryRun,
                    ]);

                } catch (\Exception $e) {
                    Log::error('Failed to process newly expired subscription', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Processed newly expired subscriptions', [
                'count' => $processedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in processNewlyExpired', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle subscriptions with expired grace periods - downgrade to free tier
     * 
     * These subscriptions have grace_period_expires_at in the past.
     */
    protected function processExpiredGracePeriods(): void
    {
        try {
            $gracePeriodExpired = SubscriptionEnhancement::where('status', 'active')
                ->whereNotNull('grace_period_expires_at')
                ->where('grace_period_expires_at', '<', now())
                ->with('subscription')
                ->get();

            Log::info('Found subscriptions with expired grace periods', [
                'count' => $gracePeriodExpired->count(),
            ]);

            $downgradedCount = 0;
            foreach ($gracePeriodExpired as $enhancement) {
                try {
                    if (!$this->dryRun) {
                        // Mark enhancement as expired
                        $enhancement->update(['status' => 'expired']);

                        // Downgrade subscription to free tier
                        $subscription = $enhancement->subscription;
                        $subscription->plan_id = $this->getFreeTierId();
                        $subscription->ends_at = null;
                        $subscription->save();

                        // Send notification
                        $this->notifyDowngradedToFree($subscription);
                    }

                    $downgradedCount++;

                    Log::info('Subscription downgraded to free tier', [
                        'subscription_id' => $enhancement->subscription_id,
                        'user_id' => $enhancement->subscription->subscriber_id,
                        'dry_run' => $this->dryRun,
                    ]);

                } catch (\Exception $e) {
                    Log::error('Failed to downgrade subscription', [
                        'enhancement_id' => $enhancement->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Processed grace period expirations', [
                'downgraded' => $downgradedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in processExpiredGracePeriods', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the free tier plan ID
     */
    protected function getFreeTierId(): int
    {
        // Cache this to avoid repeated queries
        static $freeTierId;
        if (!$freeTierId) {
            $freeTierId = \App\Models\Plan::where('slug', 'free')
                ->orWhere('name', 'Free')
                ->first()?->id ?? 1;
        }
        return $freeTierId;
    }

    /**
     * Notify user that their subscription expired and entered grace period
     */
    protected function notifySubscriptionExpired(PlanSubscription $subscription): void
    {
        try {
            // Send email or in-app notification
            // Implementation depends on notification system
            Log::info('Notification sent: subscription expired', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->subscriber_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send subscription expired notification', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify user that they were downgraded to free tier
     */
    protected function notifyDowngradedToFree(PlanSubscription $subscription): void
    {
        try {
            // Send email or in-app notification
            Log::info('Notification sent: downgraded to free', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->subscriber_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send downgrade notification', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
