<?php

namespace App\Services;

use App\Models\PlanSubscription;
use App\Models\RenewalReminder;
use Illuminate\Support\Facades\Log;

class RenewalReminderService
{
    /**
     * Schedule standard reminders (7d, 3d, 1d) for a subscription
     */
    public function scheduleRemindersForSubscription(PlanSubscription $subscription): void
    {
        $subscriber = $subscription->subscriber;
        $endsAt = $subscription->ends_at;

        if (!$endsAt) {
            // Can't schedule reminders without an end date
            Log::warning('Subscription has no ends_at, skipping reminders', ['subscription_id' => $subscription->id]);
            return;
        }

        $days = [7 => 'seven_days', 3 => 'three_days', 1 => 'one_day'];

        foreach ($days as $d => $type) {
            $scheduledAt = $endsAt->copy()->subDays($d)->startOfDay();

            if ($scheduledAt->isPast()) {
                continue; // Skip past reminders
            }

            RenewalReminder::updateOrCreate(
                ['subscription_id' => $subscription->id, 'reminder_type' => $type],
                [
                    'user_id' => $subscriber ? $subscriber->id : null,
                    'days_before_expiry' => $d,
                    'scheduled_at' => $scheduledAt,
                    'is_sent' => false,
                    'channel' => 'email',
                    'metadata' => [
                        'plan_id' => $subscription->plan_id,
                        'ends_at' => $endsAt->toDateTimeString(),
                    ],
                ]
            );
        }
    }
}
