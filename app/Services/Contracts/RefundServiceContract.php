<?php

namespace App\Services\Contracts;

use App\Models\Donation;
use App\Models\EventOrder;
use App\Models\LabBooking;
use App\Models\PlanSubscription;
use App\Models\Refund;
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
     */
    public function listRefunds(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Submit a generic refund request (used by public and admin endpoints)
     */
    public function submitRefundRequest(
        string $refundableType,
        int $refundableId,
        string $reason,
        ?string $customerNotes = null,
        ?float $amount = null
    ): Refund;

    /**
     * Request a refund for an event order
     */
    public function requestEventOrderRefund(
        EventOrder $order,
        string $reason,
        ?string $customerNotes = null,
        ?float $amount = null
    ): Refund;

    /**
     * Request a refund for a donation
     */
    public function requestDonationRefund(
        Donation $donation,
        string $reason,
        ?string $customerNotes = null
    ): Refund;

    /**
     * Request a refund for a subscription
     */
    public function requestSubscriptionRefund(
        PlanSubscription $subscription,
        string $reason,
        ?string $customerNotes = null,
        ?float $amount = null
    ): Refund;

    /**
     * Request a refund for a lab booking
     */
    public function requestLabBookingRefund(
        LabBooking $booking,
        string $reason,
        ?string $customerNotes = null
    ): Refund;

    /**
     * Get preview of potential refund for a lab booking
     */
    public function getLabBookingRefundPreview(LabBooking $booking): array;

    /**
     * Approve a pending refund
     */
    public function approveRefund(Refund $refund, Authenticatable $admin, ?string $adminNotes = null): Refund;

    /**
     * Reject a pending refund
     */
    public function rejectRefund(Refund $refund, Authenticatable $admin, ?string $reason = null): Refund;

    /**
     * Process an approved refund via gateway
     */
    public function processRefund(Refund $refund): Refund;

    /**
     * Get refund statistics
     */
    public function getStats(): array;
}
