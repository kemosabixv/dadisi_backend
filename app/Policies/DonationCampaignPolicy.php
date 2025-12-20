<?php

namespace App\Policies;

use App\Models\DonationCampaign;
use App\Models\User;

class DonationCampaignPolicy
{
    /**
     * Determine whether the user can view any models.
     * Public: anyone can view the list of active campaigns.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Public: anyone can view an active campaign.
     */
    public function view(?User $user, DonationCampaign $campaign): bool
    {
        // Public can only view active/published campaigns
        if ($campaign->status !== 'active') {
            // Only staff can view non-active campaigns
            return $user && $this->isStaff($user);
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DonationCampaign $campaign): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DonationCampaign $campaign): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DonationCampaign $campaign): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DonationCampaign $campaign): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can publish the campaign.
     */
    public function publish(User $user, DonationCampaign $campaign): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Determine whether the user can unpublish the campaign.
     */
    public function unpublish(User $user, DonationCampaign $campaign): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Determine whether the user can mark the campaign as complete.
     */
    public function complete(User $user, DonationCampaign $campaign): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Check if user is staff (Admin, Finance, or Content Editor).
     */
    private function isStaff(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'finance', 'content_editor']);
    }
}
