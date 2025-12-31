<?php

namespace App\Services\Contracts;

use App\Models\Donation;
use App\Models\EventOrder;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * RefundServiceContract
 *
 * Defines contract for refund management across the platform.
 */
interface RefundServiceContract
{
    /**
     * List all refunds with filtering
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function listRefunds(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Request a refund for an event order
     *
     * @param EventOrder $order
     * @param string $reason
     * @param string|null $customerNotes
     * @param float|null $amount
     * @return Refund
     */
    public function requestEventOrderRefund(
        EventOrder $order,
        string $reason,
        ?string $customerNotes = null,
        ?float $amount = null
    ): Refund;

    /**
     * Request a refund for a donation
     *
     * @param Donation $donation
     * @param string $reason
     * @param string|null $customerNotes
     * @return Refund
     */
    public function requestDonationRefund(
        Donation $donation,
        string $reason,
        ?string $customerNotes = null
    ): Refund;

    /**
     * Approve a pending refund
     *
     * @param Refund $refund
     * @param Authenticatable $admin
     * @param string|null $adminNotes
     * @return Refund
     */
    public function approveRefund(Refund $refund, Authenticatable $admin, ?string $adminNotes = null): Refund;

    /**
     * Reject a pending refund
     *
     * @param Refund $refund
     * @param Authenticatable $admin
     * @param string $reason
     * @return Refund
     */
    public function rejectRefund(Refund $refund, Authenticatable $admin, string $reason): Refund;

    /**
     * Process an approved refund via gateway
     *
     * @param Refund $refund
     * @return Refund
     */
    public function processRefund(Refund $refund): Refund;

    /**
     * Get refund statistics
     *
     * @return array
     */
    public function getStats(): array;
}
