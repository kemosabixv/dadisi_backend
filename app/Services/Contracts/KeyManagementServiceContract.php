<?php

namespace App\Services\Contracts;

/**
 * Key Management Service Contract
 *
 * Defines the interface for user public key management for encrypted messaging.
 */
interface KeyManagementServiceContract
{
    /**
     * Store or update authenticated user's public key
     *
     * @param string $publicKey The public key content
     * @return array Public key data
     */
    public function storePublicKey(string $publicKey): array;

    /**
     * Get a user's public key by user ID
     *
     * @param int $userId
     * @return array|null Public key data or null if not found
     */
    public function getUserPublicKey(int $userId): ?array;

    /**
     * Get authenticated user's public key
     *
     * @return array|null Public key data or null if not set
     */
    public function getMyPublicKey(): ?array;

    /**
     * Delete authenticated user's public key
     *
     * @return bool
     */
    public function deleteMyPublicKey(): bool;
}
