<?php

namespace App\Services\Contracts;

use App\Models\Donation;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\Paginator;

/**
 * DonationServiceContract
 *
 * Defines contract for donation management including creation,
 * tracking, and verification of donations.
 */
interface DonationServiceContract
{
    /**
     * Create a new donation
     */
    public function createDonation(?Authenticatable $donor, array $data): Donation;

    /**
     * Get donation by ID
     */
    public function getDonation(string $id): Donation;

    /**
     * Get donation by reference
     */
    public function getDonationByReference(string $reference): Donation;

    /**
     * List donations with filtering
     * Filters: county_id, user_id, campaign_id, status, search, start_date, end_date
     */
    public function listDonations(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    /**
     * Mark donation as paid
     */
    public function markAsPaid(Donation $donation, array $paymentData = [], ?\Illuminate\Contracts\Auth\Authenticatable $actor = null): Donation;

    /**
     * Delete/Cancel donation
     */
    public function deleteDonation(Authenticatable $actor, Donation $donation): bool;

    /**
     * Get donor donations history
     */
    public function getDonorHistory(Authenticatable $donor, int $limit = 50): \Illuminate\Database\Eloquent\Collection;

    /**
     * Get donation statistics
     */
    public function getStatistics(array $filters = []): array;

    /**
     * Generate donation report
     */
    public function generateReport(array $filters, string $format = 'csv'): string;

    /**
     * Get counties list for forms.
     */
    public function getCounties(): \Illuminate\Database\Eloquent\Collection;
}
