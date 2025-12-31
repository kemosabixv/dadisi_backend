<?php

namespace App\Services\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Webhook Service Contract
 *
 * Defines the interface for webhook handling and management.
 */
interface WebhookServiceContract
{
    /**
     * Process Pesapal webhook notification
     *
     * @param array $payload Webhook payload
     * @param string $signature HMAC signature
     * @return array Response data
     */
    public function processPesapalWebhook(array $payload, string $signature): array;

    /**
     * List webhook events with optional filtering
     *
     * @param array $filters Filtering options
     * @return LengthAwarePaginator
     */
    public function listWebhookEvents(array $filters = []): LengthAwarePaginator;
}
