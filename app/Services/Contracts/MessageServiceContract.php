<?php

namespace App\Services\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Message Service Contract
 *
 * Defines the interface for encrypted message operations including conversations and vault access.
 */
interface MessageServiceContract
{
    /**
     * Get pre-signed upload URL for encrypted message
     *
     * @return array Upload URL and object key
     */
    public function getUploadUrl(): array;

    /**
     * Store message metadata after encrypted content upload
     *
     * @param array $data Message metadata
     * @return array Message data
     */
    public function sendMessage(array $data): array;

    /**
     * Get user's conversations (grouped by partner)
     *
     * @return array List of conversations
     */
    public function getConversations(): array;

    /**
     * Get messages in a conversation with a specific user
     *
     * @param int $partnerId
     * @return LengthAwarePaginator
     */
    public function getConversation(int $partnerId): LengthAwarePaginator;

    /**
     * Get pre-signed download URL for encrypted message
     *
     * @param int $messageId
     * @return array Download URL and encryption metadata
     */
    public function getVaultUrl(int $messageId): array;
}
