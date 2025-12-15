<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\PlanSubscription;

class PlanSubscriptionObserver
{
    /**
     * Handle the PlanSubscription "deleting" event.
     * 
     * Cascade delete related enhancements to maintain data integrity
     * since foreign key constraints cannot be enforced on polymorphic relationships.
     */
    public function deleting(PlanSubscription $subscription): void
    {
        // Delete all related subscription enhancements
        $subscription->enhancements()->delete();
    }

    /**
     * Handle the PlanSubscription "deleted" event.
     */
    public function deleted(PlanSubscription $subscription): void
    {
        // Additional cleanup if needed
    }
}
