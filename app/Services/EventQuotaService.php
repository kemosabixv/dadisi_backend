<?php

namespace App\Services;

use App\Models\User;
use App\Models\PlanFeature;
use Illuminate\Support\Facades\DB;

class EventQuotaService
{
    /**
     * Get the limit value for a specific feature slug from the user's current plan.
     * 
     * @param User $user
     * @param string $featureSlug
     * @return int|null -1 for unlimited, 0 for not allowed, >0 for limit
     */
    public function getFeatureLimit(User $user, string $featureSlug): int
    {
        $plan = $user->plan;
        if (!$plan) {
            return 0; // No plan, no features
        }

        $feature = $plan->features()->where('slug', $featureSlug)->first();
        
        if (!$feature) {
            return 0; // Feature not defined for this plan
        }

        return (int) $feature->value;
    }

    /**
     * Check if user can create a new event based on their monthly quota.
     */
    public function canCreateEvent(User $user): bool
    {
        // 1. Staff members have unlimited creation power
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
     * Check if user can participate in an event based on their monthly quota.
     */
    public function canParticipate(User $user): bool
    {
        $limit = $this->getFeatureLimit($user, 'event_participation_limit');

        if ($limit === -1) {
            return true;
        }

        if ($limit === 0) {
            return false;
        }

        $usage = $this->getMonthlyParticipationUsage($user);

        return $usage < $limit;
    }

    /**
     * Get remaining creations for the current month.
     */
    public function getRemainingCreations(User $user): ?int
    {
        if ($user->isStaffMember()) return null; // Unlimited

        $limit = $this->getFeatureLimit($user, 'event_creation_limit');
        if ($limit === -1) return null; // Unlimited

        $usage = $this->getMonthlyCreationUsage($user);
        return max(0, $limit - $usage);
    }

    /**
     * Get remaining participations for the current month.
     */
    public function getRemainingParticipations(User $user): ?int
    {
        $limit = $this->getFeatureLimit($user, 'event_participation_limit');
        if ($limit === -1) return null; // Unlimited

        $usage = $this->getMonthlyParticipationUsage($user);
        return max(0, $limit - $usage);
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

    /**
     * Get usage count for event participations in the current calendar month.
     */
    public function getMonthlyParticipationUsage(User $user): int
    {
        return $user->registrations()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
    }
}
