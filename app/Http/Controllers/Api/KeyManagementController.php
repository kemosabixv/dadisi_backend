<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePublicKeyRequest;
use App\Services\Contracts\KeyManagementServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class KeyManagementController extends Controller
{
    public function __construct(private KeyManagementServiceContract $keyManagementService)
    {
    }
    /**
     * Store or update the authenticated user's public key.
     */
    public function store(StorePublicKeyRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $publicKey = $this->keyManagementService->storePublicKey($validated['public_key']);

            return response()->json([
                'success' => true,
                'data' => $publicKey,
                'message' => 'Public key stored successfully.',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to store public key', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to store public key'], 500);
        }
    }

    /**
     * Get a user's public key by user ID.
     */
    public function show(int $userId): JsonResponse
    {
        try {
            $publicKey = $this->keyManagementService->getUserPublicKey($userId);

            if (!$publicKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'User has not set up encrypted messaging.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $publicKey,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve public key', ['error' => $e->getMessage(), 'user_id' => $userId]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve public key'], 500);
        }
    }

    /**
     * Get the authenticated user's public key.
     */
    public function me(): JsonResponse
    {
        try {
            $publicKey = $this->keyManagementService->getMyPublicKey();

            return response()->json([
                'success' => true,
                'data' => $publicKey,
                'has_key' => (bool) $publicKey,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user public key', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve public key'], 500);
        }
    }

    /**
     * Delete the authenticated user's public key.
     */
    public function destroy(): JsonResponse
    {
        try {
            $this->keyManagementService->deleteMyPublicKey();

            return response()->json([
                'success' => true,
                'message' => 'Public key deleted. Encrypted messaging is now disabled.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete public key', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to delete public key'], 500);
        }
    }
}
