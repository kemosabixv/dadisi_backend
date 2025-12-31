<?php

namespace App\Services\Contracts;

use App\Models\Event;
use App\Models\EventOrder;
use App\Models\User;

interface EventOrderServiceContract
{
    /**
     * Create a new ticket order and initiate payment.
     *
     * @param Event $event
     * @param int $quantity
     * @param array $purchaserData User data or guest data
     * @param string|null $promoCode
     * @param User|null $user Authenticated user (null for guest)
     * @return array
     */
    public function createOrder(
        Event $event,
        int $quantity,
        array $purchaserData,
        ?string $promoCode = null,
        ?User $user = null
    ): array;

    /**
     * Mark order as paid (called from webhook/callback).
     */
    public function markOrderPaid(EventOrder $order, string $transactionId): void;

    /**
     * Check in an attendee using their QR code token.
     */
    public function checkIn(string $qrToken, Event $event): array;

    /**
     * Check payment status of an order by reference.
     */
    public function checkPaymentStatus(string $reference): array;

    /**
     * Get order details for a specific user.
     */
    public function getOrderDetails(User $user, int $orderId): array;

    /**
     * Get user's orders with filters.
     */
    public function getUserOrders(User $user, array $filters, int $perPage): array;
}
