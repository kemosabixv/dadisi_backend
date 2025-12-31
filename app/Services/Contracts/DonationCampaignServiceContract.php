<?php

namespace App\Services\Contracts;

use App\Models\DonationCampaign;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;

interface DonationCampaignServiceContract
{
    /**
     * List campaigns with filters.
     */
    public function listCampaigns(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * List active campaigns for public consumption.
     */
    public function listActiveCampaigns(array $filters = [], int $perPage = 12): LengthAwarePaginator;

    /**
     * Get campaign by slug.
     */
    public function getCampaignBySlug(string $slug): DonationCampaign;

    /**
     * Get campaign by ID.
     */
    public function getCampaign(int $id): DonationCampaign;

    /**
     * Create a new campaign.
     */
    public function createCampaign(Authenticatable $actor, array $data): DonationCampaign;

    /**
     * Update a campaign.
     */
    public function updateCampaign(Authenticatable $actor, DonationCampaign $campaign, array $data): DonationCampaign;

    /**
     * Delete a campaign (soft).
     */
    public function deleteCampaign(Authenticatable $actor, DonationCampaign $campaign): bool;

    /**
     * Restore a soft-deleted campaign.
     */
    public function restoreCampaign(Authenticatable $actor, string $slug): DonationCampaign;

    /**
     * Publish a campaign.
     */
    public function publishCampaign(Authenticatable $actor, DonationCampaign $campaign): DonationCampaign;

    /**
     * Unpublish a campaign.
     */
    public function unpublishCampaign(Authenticatable $actor, DonationCampaign $campaign): DonationCampaign;

    /**
     * Complete a campaign.
     */
    public function completeCampaign(Authenticatable $actor, DonationCampaign $campaign): DonationCampaign;

    /**
     * Get counties list for forms.
     */
    public function getCounties(): \Illuminate\Database\Eloquent\Collection;

    /**
     * Validate a campaign for donation.
     */
    public function validateCampaignForDonation(DonationCampaign $campaign, float $amount): void;
}
