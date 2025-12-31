<?php

namespace App\Services\PromoCodes;

use App\Exceptions\PromoCodeException;
use App\Models\AuditLog;
use App\Models\PromoCode;
use App\Services\Contracts\PromoCodeServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PromoCodeService
 *
 * Handles promo code management including creation, redemption,
 * and tracking usage statistics.
 */
class PromoCodeService implements PromoCodeServiceContract
{
    /**
     * Create a new promo code
     *
     * @param Authenticatable $creator
     * @param array $data
     * @return PromoCode
     *
     * @throws PromoCodeException
     */
    public function createPromoCode(Authenticatable $creator, array $data): PromoCode
    {
        try {
            return DB::transaction(function () use ($creator, $data) {
                $code = $data['code'] ?? Str::upper(Str::random(8));

                // Check if code already exists
                if (PromoCode::where('code', $code)->exists()) {
                    throw PromoCodeException::codeAlreadyExists();
                }

                $promoCode = PromoCode::create([
                    'event_id' => $data['event_id'],
                    'code' => $code,
                    'description' => $data['description'] ?? null,
                    'discount_type' => $data['discount_type'], // 'percentage' or 'fixed'
                    'discount_value' => $data['discount_value'],
                    'usage_limit' => $data['usage_limit'] ?? null,
                    'used_count' => 0,
                    'valid_from' => $data['valid_from'] ?? now(),
                    'valid_until' => $data['valid_until'] ?? null,
                    'is_active' => true,
                ]);

                AuditLog::create([
                    'actor_id' => $creator->getAuthIdentifier(),
                    'action' => 'created_promo_code',
                    'model_type' => PromoCode::class,
                    'model_id' => $promoCode->id,
                    'new_values' => [
                        'code' => $promoCode->code,
                        'discount_value' => $promoCode->discount_value,
                    ],
                ]);

                Log::info('Promo code created', [
                    'promo_code_id' => $promoCode->id,
                    'code' => $code,
                    'creator_id' => $creator->getAuthIdentifier(),
                ]);

                return $promoCode;
            });
        } catch (\Exception $e) {
            if ($e instanceof PromoCodeException) {
                throw $e;
            }

            Log::error('Promo code creation failed', [
                'creator_id' => $creator->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);

            throw PromoCodeException::creationFailed($e->getMessage());
        }
    }

    /**
     * Update a promo code
     *
     * @param Authenticatable $actor
     * @param PromoCode $promoCode
     * @param array $data
     * @return PromoCode
     *
     * @throws PromoCodeException
     */
    public function updatePromoCode(Authenticatable $actor, PromoCode $promoCode, array $data): PromoCode
    {
        try {
            $updateData = [];

            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }

            if (isset($data['discount_value'])) {
                $updateData['discount_value'] = $data['discount_value'];
            }

            if (isset($data['usage_limit'])) {
                $updateData['usage_limit'] = $data['usage_limit'];
            }

            if (isset($data['valid_until'])) {
                $updateData['valid_until'] = $data['valid_until'];
            }

            $promoCode->update($updateData);

            AuditLog::create([
                'actor_id' => $actor->getAuthIdentifier(),
                'action' => 'updated_promo_code',
                'model_type' => PromoCode::class,
                'model_id' => $promoCode->id,
                'changes' => $updateData,
            ]);

            Log::info('Promo code updated', [
                'promo_code_id' => $promoCode->id,
                'updated_by' => $actor->getAuthIdentifier(),
            ]);

            return $promoCode->fresh();
        } catch (\Exception $e) {
            throw PromoCodeException::updateFailed($e->getMessage());
        }
    }

    /**
     * Validate and redeem a promo code
     *
     * @param string $code
     * @param Authenticatable $user
     * @return PromoCode
     *
     * @throws PromoCodeException
     */
    public function redeemPromoCode(string $code, Authenticatable $user): PromoCode
    {
        try {
            return DB::transaction(function () use ($code, $user) {
                $promoCode = PromoCode::where('code', $code)->firstOrFail();

                // Validate code
                if (!$promoCode->is_active) {
                    throw PromoCodeException::codeInactive();
                }

                if ($promoCode->valid_from && now()->isBefore($promoCode->valid_from)) {
                    throw PromoCodeException::codeNotYetValid();
                }

                if ($promoCode->valid_until && now()->isAfter($promoCode->valid_until)) {
                    throw PromoCodeException::codeExpired();
                }

                if ($promoCode->usage_limit && $promoCode->usage_count >= $promoCode->usage_limit) {
                    throw PromoCodeException::usageLimitReached();
                }

                // Increment usage count
                $promoCode->increment('usage_count');

                AuditLog::create([
                    'actor_id' => $user->getAuthIdentifier(),
                    'action' => 'redeemed_promo_code',
                    'model_type' => PromoCode::class,
                    'model_id' => $promoCode->id,
                    'new_values' => ['usage_count' => $promoCode->usage_count],
                ]);

                Log::info('Promo code redeemed', [
                    'promo_code_id' => $promoCode->id,
                    'code' => $code,
                    'user_id' => $user->getAuthIdentifier(),
                ]);

                return $promoCode->fresh();
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw PromoCodeException::codeNotFound($code);
        } catch (\Exception $e) {
            if ($e instanceof PromoCodeException) {
                throw $e;
            }

            throw PromoCodeException::redemptionFailed($e->getMessage());
        }
    }

    /**
     * Deactivate a promo code
     *
     * @param Authenticatable $actor
     * @param PromoCode $promoCode
     * @return PromoCode
     *
     * @throws PromoCodeException
     */
    public function deactivatePromoCode(Authenticatable $actor, PromoCode $promoCode): PromoCode
    {
        try {
            $promoCode->update(['is_active' => false]);

            AuditLog::create([
                'actor_id' => $actor->getAuthIdentifier(),
                'action' => 'deactivated_promo_code',
                'model_type' => PromoCode::class,
                'model_id' => $promoCode->id,
                'new_values' => ['is_active' => false],
            ]);

            Log::info('Promo code deactivated', [
                'promo_code_id' => $promoCode->id,
                'deactivated_by' => $actor->getAuthIdentifier(),
            ]);

            return $promoCode->fresh();
        } catch (\Exception $e) {
            throw PromoCodeException::deactivationFailed($e->getMessage());
        }
    }

    /**
     * List promo codes
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function listPromoCodes(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PromoCode::query();

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search']) && $filters['search']) {
            $query->where('code', 'like', "%{$filters['search']}%");
        }

        if (isset($filters['discount_type']) && $filters['discount_type']) {
            $query->where('discount_type', $filters['discount_type']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get promo code statistics
     *
     * @param PromoCode $promoCode
     * @return array
     */
    public function getStatistics(PromoCode $promoCode): array
    {
        return [
            'code' => $promoCode->code,
            'discount_value' => $promoCode->discount_value,
            'discount_type' => $promoCode->discount_type,
            'used_count' => $promoCode->used_count,
            'usage_limit' => $promoCode->usage_limit,
            'remaining_uses' => $promoCode->usage_limit ? $promoCode->usage_limit - $promoCode->used_count : null,
            'is_active' => $promoCode->is_active,
            'valid_from' => $promoCode->valid_from,
            'valid_until' => $promoCode->valid_until,
        ];
    }

    /**
     * Generate a promo code with auto-generated code
     *
     * @param Authenticatable $creator
     * @param array $data
     * @return PromoCode
     *
     * @throws PromoCodeException
     */
    public function generatePromoCode(Authenticatable $creator, array $data): PromoCode
    {
        $length = $data['length'] ?? 8;
        $data['code'] = Str::upper(Str::random($length));

        // Ensure uniqueness
        while (PromoCode::where('code', $data['code'])->exists()) {
            $data['code'] = Str::upper(Str::random($length));
        }

        try {
            return DB::transaction(function () use ($creator, $data) {
                $promoCode = PromoCode::create([
                    'event_id' => $data['event_id'],
                    'code' => $data['code'],
                    'description' => $data['description'] ?? null,
                    'discount_type' => $data['discount_type'],
                    'discount_value' => $data['discount_value'],
                    'usage_limit' => $data['usage_limit'] ?? null,
                    'used_count' => 0,
                    'valid_from' => $data['valid_from'] ?? now(),
                    'valid_until' => $data['valid_until'] ?? null,
                    'is_active' => true,
                ]);

                AuditLog::create([
                    'actor_id' => $creator->getAuthIdentifier(),
                    'action' => 'generated_promo_code',
                    'model_type' => PromoCode::class,
                    'model_id' => $promoCode->id,
                    'new_values' => [
                        'code' => $promoCode->code,
                        'discount_value' => $promoCode->discount_value,
                    ],
                ]);

                Log::info('Promo code generated', [
                    'promo_code_id' => $promoCode->id,
                    'code' => $promoCode->code,
                    'creator_id' => $creator->getAuthIdentifier(),
                ]);

                return $promoCode;
            });
        } catch (\Exception $e) {
            Log::error('Promo code generation failed', [
                'creator_id' => $creator->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);

            throw PromoCodeException::creationFailed($e->getMessage());
        }
    }

    /**
     * Validate a promo code
     *
     * @param string $code
     * @return PromoCode
     *
     * @throws PromoCodeException
     */
    public function validateCode(string $code): PromoCode
    {
        $promoCode = PromoCode::where('code', $code)->first();

        if (!$promoCode) {
            throw PromoCodeException::codeNotFound($code);
        }

        if (!$promoCode->is_active) {
            throw PromoCodeException::codeInactive();
        }

        if ($promoCode->valid_from && now()->isBefore($promoCode->valid_from)) {
            throw PromoCodeException::codeNotYetValid();
        }

        if ($promoCode->valid_until && now()->isAfter($promoCode->valid_until)) {
            throw PromoCodeException::codeExpired();
        }

        if ($promoCode->usage_limit && $promoCode->used_count >= $promoCode->usage_limit) {
            throw PromoCodeException::usageLimitReached();
        }

        return $promoCode;
    }

    /**
     * Record usage of a promo code
     *
     * @param PromoCode $promoCode
     * @return PromoCode
     */
    public function recordUsage(PromoCode $promoCode): PromoCode
    {
        $promoCode->increment('used_count');

        AuditLog::create([
            'actor_id' => null,
            'action' => 'recorded_promo_code_usage',
            'model_type' => PromoCode::class,
            'model_id' => $promoCode->id,
            'new_values' => ['used_count' => $promoCode->used_count],
        ]);

        Log::info('Promo code usage recorded', [
            'promo_code_id' => $promoCode->id,
            'used_count' => $promoCode->used_count,
        ]);

        return $promoCode->fresh();
    }

    /**
     * Activate a promo code
     *
     * @param Authenticatable $actor
     * @param PromoCode $promoCode
     * @return PromoCode
     *
     * @throws PromoCodeException
     */
    public function activateCode(Authenticatable $actor, PromoCode $promoCode): PromoCode
    {
        try {
            $promoCode->update(['is_active' => true]);

            AuditLog::create([
                'actor_id' => $actor->getAuthIdentifier(),
                'action' => 'activated_promo_code',
                'model_type' => PromoCode::class,
                'model_id' => $promoCode->id,
                'new_values' => ['is_active' => true],
            ]);

            Log::info('Promo code activated', [
                'promo_code_id' => $promoCode->id,
                'activated_by' => $actor->getAuthIdentifier(),
            ]);

            return $promoCode->fresh();
        } catch (\Exception $e) {
            throw PromoCodeException::updateFailed($e->getMessage());
        }
    }

    /**
     * Deactivate a promo code (alias)
     *
     * @param Authenticatable $actor
     * @param PromoCode $promoCode
     * @return PromoCode
     *
     * @throws PromoCodeException
     */
    public function deactivateCode(Authenticatable $actor, PromoCode $promoCode): PromoCode
    {
        return $this->deactivatePromoCode($actor, $promoCode);
    }

    /**
     * Get a promo code by ID or code string
     *
     * @param int|string $idOrCode
     * @return PromoCode
     *
     * @throws PromoCodeException
     */
    public function getPromoCode(int|string $idOrCode): PromoCode
    {
        $query = is_numeric($idOrCode)
            ? PromoCode::where('id', $idOrCode)
            : PromoCode::where('code', $idOrCode);

        $promoCode = $query->first();

        if (!$promoCode) {
            throw PromoCodeException::codeNotFound((string) $idOrCode);
        }

        return $promoCode;
    }
}
