<?php

namespace App\Services\Contracts;

/**
 * User Payment Method Service Contract
 */
interface UserPaymentMethodServiceContract
{
    /**
     * List user payment methods
     */
    public function list($userId);

    /**
     * Create payment method
     */
    public function create($userId, array $data);

    /**
     * Update payment method
     */
    public function update($userId, $methodId, array $data);

    /**
     * Delete payment method
     */
    public function delete($userId, $methodId): bool;

    /**
     * Set payment method as primary
     */
    public function setPrimary($userId, $methodId);
}
