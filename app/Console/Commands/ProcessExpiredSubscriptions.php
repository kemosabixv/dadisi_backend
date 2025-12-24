<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PlanSubscription;
use App\Models\SubscriptionEnhancement;
use App\Models\Plan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:process-expired';

    protected $description = 'Process expired subscriptions: enter grace period or downgrade to free tier';

    public function handle()
    {
        $this->info('Processing expired subscriptions...');

        // Step 1: Find subscriptions that just expired (ends_at is past, not in grace period yet)
        $this->processNewlyExpired();

        // Step 2: Find subscriptions with expired grace period → downgrade to free
        $this->processExpiredGracePeriods();

        $this->info('Done.');
        return 0;
    }

    /**
     * Handle subscriptions that just expired - enter 14-day grace period
     */
    protected function processNewlyExpired(): void
    {
        $expired = PlanSubscription::whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->whereHas('enhancements', function ($q) {
                $q->where('status', 'active');
            })
            ->with(['enhancements', 'subscriber', 'plan'])
            ->get();

        $this->info("Found {$expired->count()} newly expired subscriptions");

        foreach ($expired as $subscription) {
            $enhancement = $subscription->enhancements->first();
            if (!$enhancement) {
                continue;
            }

            // Enter 14-day grace period
            $enhancement->enterGracePeriod(14);

            Log::info('Subscription entered grace period', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->subscriber_id,
                'grace_period_ends' => $enhancement->grace_period_expires_at,
            ]);

            $this->info("Subscription {$subscription->id} entered grace period");

            // TODO: Send grace period notification email
        }
    }

    /**
     * Handle subscriptions with expired grace period - downgrade to free
     */
    protected function processExpiredGracePeriods(): void
    {
        $expiredGrace = SubscriptionEnhancement::where('status', 'grace_period')
            ->where(function ($q) {
                $q->where('grace_period_expires_at', '<', now())
                  ->orWhere('grace_period_ends_at', '<', now());
            })
            ->with(['subscription.subscriber', 'subscription.plan'])
            ->get();

        $this->info("Found {$expiredGrace->count()} subscriptions with expired grace period");

        foreach ($expiredGrace as $enhancement) {
            $subscription = $enhancement->subscription;
            if (!$subscription) {
                continue;
            }

            $user = $subscription->subscriber;
            if (!$user) {
                continue;
            }

            // Find free plan
            $freePlan = Plan::getDefaultFreePlan();
            if (!$freePlan) {
                Log::warning('No free plan available for downgrade', [
                    'subscription_id' => $subscription->id,
                ]);
                // Just suspend if no free plan
                $enhancement->suspend();
                continue;
            }

            // Create new free subscription
            try {
                $newSubscription = $user->newSubscription($freePlan->slug, $freePlan);
                $newSubscription->starts_at = now();
                $newSubscription->save();

                // Create enhancement for new subscription
                SubscriptionEnhancement::create([
                    'subscription_id' => $newSubscription->id,
                    'status' => 'active',
                ]);

                // Update user's active subscription
                $user->update(['active_subscription_id' => $newSubscription->id]);

                // Cancel old subscription
                $subscription->cancel();
                $enhancement->cancel();

                Log::info('User downgraded to free plan', [
                    'user_id' => $user->id,
                    'old_subscription_id' => $subscription->id,
                    'new_subscription_id' => $newSubscription->id,
                    'free_plan_id' => $freePlan->id,
                ]);

                $this->info("User {$user->id} downgraded to free plan");

                // TODO: Send downgrade notification email

            } catch (\Exception $e) {
                Log::error('Failed to downgrade user to free plan', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed to downgrade user {$user->id}: {$e->getMessage()}");
            }
        }
    }
}
