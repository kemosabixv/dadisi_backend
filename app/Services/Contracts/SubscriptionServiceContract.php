<?php

namespace App\Contracts;

/**
 * Contract for Subscription Service
 *
 * Handles subscription creation, renewal, cancellation, and plan management.
 */
interface SubscriptionServiceContract
{
    /**
     * Create a new subscription
     *
     * @param array $data Subscription data
     * @return \App\Models\PlanSubscription
     */
    public function create(array $data);

    /**
     * Cancel a subscription
     *
     * @param \App\Models\PlanSubscription $subscription
     * @param string|null $reason
     * @return bool
     */
    public function cancel($subscription, ?string $reason = null): bool;

    /**
     * Renew a subscription
     *
     * @param \App\Models\PlanSubscription $subscription
     * @return \App\Models\PlanSubscription
     */
    public function renew($subscription);

    /**
     * Check if subscription needs renewal
     *
     * @param \App\Models\PlanSubscription $subscription
     * @return bool
     */
    public function needsRenewal($subscription): bool;

    /**
     * Get subscription details
     *
     * @param int $subscriptionId
     * @return \App\Models\PlanSubscription
     */
    public function getDetails(int $subscriptionId);
}
