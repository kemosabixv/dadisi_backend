<?php

namespace App\Services;

use App\Models\User;
use App\Models\Plan;

class EventQuotaService
{
    /**
     * Get the limit value for a specific feature slug from the user's current plan.
     * Uses the new SystemFeature-based approach.
     * 
     * @param User $user
     * @param string $featureSlug
     * @return int -1 for unlimited, 0 for not allowed, >0 for limit
     */
    public function getFeatureLimit(User $user, string $featureSlug): int
    {
        $plan = $this->getUserPlan($user);
        
        if (!$plan) {
            return 0; // No plan, no features
        }

        // Use the Plan's getFeatureValue method which works with SystemFeature
        $value = $plan->getFeatureValue($featureSlug, 0);
        
        // -1 represents unlimited
        if ($value === -1 || $value === '-1') {
            return -1;
        }
        
        return (int) $value;
    }

    /**
     * Get the user's current plan.
     * Checks both direct plan relationship and active subscription.
     */
    protected function getUserPlan(User $user): ?Plan
    {
        // First check direct plan relationship
        if ($user->plan) {
            return $user->plan;
        }

        // Fall back to active subscription's plan
        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->whereNull('canceled_at')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->with('plan')
            ->first();

        return $subscription?->plan;
    }

    /**
     * Check if user can create a new event based on their monthly quota.
     */
    public function canCreateEvent(User $user): bool
    {
        // Staff members have unlimited creation power
        if ($user->isStaffMember()) {
            return true;
        }

        $limit = $this->getFeatureLimit($user, 'event_creation_limit');

        if ($limit === -1) {
            return true;
        }

        if ($limit === 0) {
            return false;
        }

        $usage = $this->getMonthlyCreationUsage($user);

        return $usage < $limit;
    }

    /**
     * Check if user can purchase tickets based on their plan.
     * Note: This is different from the old participation quota.
     * Active subscribers get discounts, but guests can still purchase.
     */
    public function canPurchaseTickets(User $user): bool
    {
        // Everyone can purchase tickets
        // Subscriber discounts are handled in EventOrderService
        return true;
    }

    /**
     * Get subscriber discount percentage for ticket purchases.
     */
    public function getSubscriberDiscount(User $user): float
    {
        if (!$user->hasActiveSubscription()) {
            return 0;
        }

        $plan = $this->getUserPlan($user);
        if (!$plan) {
            return 0;
        }

        // Get the ticket discount feature value
        $discount = $plan->getFeatureValue('ticket_discount_percent', 0);
        
        return min(100, max(0, (float) $discount));
    }

    /**
     * Check if user has priority access to events.
     */
    public function hasPriorityAccess(User $user): bool
    {
        $plan = $this->getUserPlan($user);
        if (!$plan) {
            return false;
        }

        return (bool) $plan->getFeatureValue('priority_event_access', false);
    }

    /**
     * Get remaining creations for the current month.
     */
    public function getRemainingCreations(User $user): ?int
    {
        if ($user->isStaffMember()) {
            return null; // Unlimited
        }

        $limit = $this->getFeatureLimit($user, 'event_creation_limit');
        
        if ($limit === -1) {
            return null; // Unlimited
        }

        $usage = $this->getMonthlyCreationUsage($user);
        return max(0, $limit - $usage);
    }

    /**
     * Get quota status for display.
     */
    public function getQuotaStatus(User $user): array
    {
        $plan = $this->getUserPlan($user);
        
        if (!$plan) {
            return [
                'has_access' => false,
                'reason' => 'no_subscription',
            ];
        }

        $creationLimit = $this->getFeatureLimit($user, 'event_creation_limit');
        $creationUsage = $this->getMonthlyCreationUsage($user);

        return [
            'has_access' => true,
            'plan_name' => $plan->name,
            'creation' => [
                'limit' => $creationLimit === -1 ? null : $creationLimit,
                'unlimited' => $creationLimit === -1,
                'used' => $creationUsage,
                'remaining' => $creationLimit === -1 ? null : max(0, $creationLimit - $creationUsage),
            ],
            'subscriber_discount' => $this->getSubscriberDiscount($user),
            'priority_access' => $this->hasPriorityAccess($user),
            'resets_at' => now()->endOfMonth()->toISOString(),
        ];
    }

    /**
     * Get usage count for event creations in the current calendar month.
     */
    public function getMonthlyCreationUsage(User $user): int
    {
        return $user->organizedEvents()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
    }
}
