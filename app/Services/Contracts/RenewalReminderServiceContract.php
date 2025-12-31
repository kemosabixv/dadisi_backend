<?php

namespace App\Services\Contracts;

use App\Models\PlanSubscription;

interface RenewalReminderServiceContract
{
    /**
     * Schedule renewal reminders for a subscription.
     */
    public function scheduleRemindersForSubscription(PlanSubscription $subscription): void;
}
