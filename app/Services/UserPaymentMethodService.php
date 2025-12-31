<?php

namespace App\Services;

use App\Services\Contracts\UserPaymentMethodServiceContract;
use App\Models\UserPaymentMethod;
use Illuminate\Support\Facades\Log;

/**
 * User Payment Method Service
 */
class UserPaymentMethodService implements UserPaymentMethodServiceContract
{
    public function list($userId)
    {
        try {
            return UserPaymentMethod::where('user_id', $userId)->get();
        } catch (\Exception $e) {
            Log::error('Failed to retrieve payment methods', ['error' => $e->getMessage(), 'user_id' => $userId]);
            throw $e;
        }
    }

    public function create($userId, array $data)
    {
        try {
            $method = UserPaymentMethod::create(array_merge($data, ['user_id' => $userId]));

            if (!empty($data['is_primary'])) {
                UserPaymentMethod::where('user_id', $userId)->where('id', '!=', $method->id)->update(['is_primary' => false]);
                $method->is_primary = true;
                $method->save();
            }

            return $method;
        } catch (\Exception $e) {
            Log::error('Failed to create payment method', ['error' => $e->getMessage(), 'user_id' => $userId]);
            throw $e;
        }
    }

    public function update($userId, $methodId, array $data)
    {
        try {
            $method = UserPaymentMethod::where('user_id', $userId)->where('id', $methodId)->firstOrFail();
            $method->update($data);

            if (array_key_exists('is_primary', $data) && $data['is_primary']) {
                UserPaymentMethod::where('user_id', $userId)->where('id', '!=', $methodId)->update(['is_primary' => false]);
                $method->is_primary = true;
                $method->save();
            }

            return $method;
        } catch (\Exception $e) {
            Log::error('Failed to update payment method', ['error' => $e->getMessage(), 'user_id' => $userId, 'method_id' => $methodId]);
            throw $e;
        }
    }

    public function delete($userId, $methodId): bool
    {
        try {
            $method = UserPaymentMethod::where('user_id', $userId)->where('id', $methodId)->firstOrFail();
            return (bool) $method->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete payment method', ['error' => $e->getMessage(), 'user_id' => $userId, 'method_id' => $methodId]);
            throw $e;
        }
    }

    public function setPrimary($userId, $methodId)
    {
        try {
            $method = UserPaymentMethod::where('user_id', $userId)->where('id', $methodId)->firstOrFail();
            UserPaymentMethod::where('user_id', $userId)->update(['is_primary' => false]);
            $method->is_primary = true;
            $method->save();
            return $method;
        } catch (\Exception $e) {
            Log::error('Failed to set primary payment method', ['error' => $e->getMessage(), 'user_id' => $userId, 'method_id' => $methodId]);
            throw $e;
        }
    }
}
