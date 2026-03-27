<?php

namespace App\Jobs;

use App\Models\PlanSubscription;
use App\Models\RenewalPreference;
use App\Notifications\SubscriptionReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendSubscriptionRemindersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     *
     * @param bool $dryRun If true, report what would be sent without sending
     */
    public function __construct(
        protected bool $dryRun = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get all active subscriptions that are not cancelled and not expired
            $subscriptions = PlanSubscription::where('status', 'active')
                ->whereNull('cancels_at')
                ->where('ends_at', '>', now())
                ->with(['subscriber', 'plan'])
                ->lockForUpdate()
                ->get();

            $sentCount = 0;
            $failedCount = 0;

            Log::info('SendSubscriptionRemindersJob started', [
                'subscriptions_count' => $subscriptions->count(),
                'dry_run' => $this->dryRun,
            ]);

            foreach ($subscriptions as $subscription) {
                try {
                    $user = $subscription->subscriber;
                    if (!$user) {
                        continue;
                    }

                    // Get user's renewal preferences or use defaults
                    $preferences = RenewalPreference::where('user_id', $user->id)->first() ?? new RenewalPreference([
                        'user_id' => $user->id,
                        'days_before_expiry' => 7,  // Default: 7 days before expiry
                    ]);

                    $daysUntilExpiry = now()->diffInDays($subscription->ends_at, false);

                    // Send reminder if within the preference window
                    if ($daysUntilExpiry <= $preferences->days_before_expiry && $daysUntilExpiry > 0) {
                        if (!$this->dryRun) {
                            Notification::send($user, new SubscriptionReminder($subscription));
                        }

                        $sentCount++;
                    }

                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error('Failed to send subscription reminder', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('SendSubscriptionRemindersJob completed', [
                'sent' => $sentCount,
                'failed' => $failedCount,
                'dry_run' => $this->dryRun,
            ]);

        } catch (\Exception $e) {
            Log::error('SendSubscriptionRemindersJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
