<?php

namespace App\Services;

use App\Services\Contracts\KeyManagementServiceContract;
use App\Models\UserPublicKey;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Key Management Service
 *
 * Handles user public key operations for encrypted messaging setup.
 */
class KeyManagementService implements KeyManagementServiceContract
{
    /**
     * Store or update authenticated user's public key
     */
    public function storePublicKey(string $publicKey): array
    {
        try {
            $userKey = UserPublicKey::updateOrCreate(
                ['user_id' => Auth::id()],
                ['public_key' => $publicKey]
            );

            return $userKey->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to store public key', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);
            throw $e;
        }
    }

    /**
     * Get a user's public key by user ID
     */
    public function getUserPublicKey(int $userId): ?array
    {
        try {
            $publicKey = UserPublicKey::where('user_id', $userId)->first();

            if (!$publicKey) {
                return null;
            }

            return [
                'user_id' => $publicKey->user_id,
                'public_key' => $publicKey->public_key,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve public key', ['error' => $e->getMessage(), 'user_id' => $userId]);
            throw $e;
        }
    }

    /**
     * Get authenticated user's public key
     */
    public function getMyPublicKey(): ?array
    {
        try {
            $publicKey = Auth::user()->publicKey;

            if (!$publicKey) {
                return null;
            }

            return $publicKey->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user public key', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);
            throw $e;
        }
    }

    /**
     * Delete authenticated user's public key
     */
    public function deleteMyPublicKey(): bool
    {
        try {
            return (bool) Auth::user()->publicKey?->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete public key', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);
            throw $e;
        }
    }
}
