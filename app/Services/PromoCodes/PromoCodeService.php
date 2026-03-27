<?php

namespace App\Services\PromoCodes;

use App\DTOs\CreatePromoCodeDTO;
use App\DTOs\UpdatePromoCodeDTO;
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
     * @param CreatePromoCodeDTO $dto
     * @return PromoCode
     *
     * @throws PromoCodeException
     */
    public function createPromoCode(Authenticatable $creator, CreatePromoCodeDTO $dto): PromoCode
    {
        try {
            return DB::transaction(function () use ($creator, $dto) {
                $data = $dto->toArray();
                // Validate length if manual code provided
                if (isset($data['code']) && (strlen($data['code']) < 3 || strlen($data['code']) > 20)) {
                    throw PromoCodeException::creationFailed('Promo code must be between 3 and 20 characters.');
                }

                $code = isset($data['code']) ? strtoupper($data['code']) : Str::upper(Str::random(8));

                // Check if code already exists
                if (PromoCode::where('code', $code)->exists()) {
                    throw PromoCodeException::codeAlreadyExists();
                }

                $promoCode = PromoCode::create([
                    'event_id' => $data['event_id'],
                    'ticket_id' => $data['ticket_id'] ?? null,
                    'code' => $code,
                    'discount_type' => $data['discount_type'], // 'percentage' or 'fixed'
                    'discount_value' => $data['discount_value'],
                    'usage_limit' => $data['usage_limit'] ?? null,
                    'used_count' => 0,
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
     * @param UpdatePromoCodeDTO $dto
     * @return PromoCode
     *
     * @throws PromoCodeException
     */
    public function updatePromoCode(Authenticatable $actor, PromoCode $promoCode, UpdatePromoCodeDTO $dto): PromoCode
    {
        try {
            $data = array_filter($dto->toArray(), fn($v) => $v !== null);
            $updateData = [];

            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }

            if (isset($data['discount_value'])) {
                $updateData['discount_value'] = $data['discount_value'];
            }

            if (isset($data['ticket_id'])) {
                $updateData['ticket_id'] = $data['ticket_id'];
            }

            if (isset($data['usage_limit'])) {
                $updateData['usage_limit'] = $data['usage_limit'];
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

                if ($promoCode->usage_limit && $promoCode->used_count >= $promoCode->usage_limit) {
                    throw PromoCodeException::usageLimitReached();
                }

                // Increment usage count
                $promoCode->increment('used_count');

                AuditLog::create([
                    'actor_id' => $user->getAuthIdentifier(),
                    'action' => 'redeemed_promo_code',
                    'model_type' => PromoCode::class,
                    'model_id' => $promoCode->id,
                    'new_values' => ['used_count' => $promoCode->used_count],
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

        // Enforce length logic
        if ($length < 3) $length = 3;
        if ($length > 20) $length = 20;

        $data['code'] = Str::upper(Str::random($length));

        // Ensure uniqueness
        while (PromoCode::where('code', $data['code'])->exists()) {
            $data['code'] = Str::upper(Str::random($length));
        }

        try {
            return DB::transaction(function () use ($creator, $data) {
                $promoCode = PromoCode::create([
                    'event_id' => $data['event_id'],
                    'ticket_id' => $data['ticket_id'] ?? null,
                    'code' => $data['code'],
                    'discount_type' => $data['discount_type'],
                    'discount_value' => $data['discount_value'],
                    'usage_limit' => $data['usage_limit'] ?? null,
                    'used_count' => 0,
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

    /**
     * Validate a promo code for a specific event and ticket tier
     *
     * @param string $code
     * @param int $eventId
     * @param int|null $ticketId
     * @return PromoCode
     *
     * @throws PromoCodeException
     */
    public function validateForEvent(string $code, int $eventId, ?int $ticketId = null): PromoCode
    {
        $promoCode = $this->validateCode($code);

        // Check event restriction
        if ($promoCode->event_id && $promoCode->event_id !== $eventId) {
            throw new PromoCodeException("This promo code is not valid for this event.");
        }

        // Check ticket tier restriction
        if ($promoCode->ticket_id && $ticketId && $promoCode->ticket_id !== $ticketId) {
            throw new PromoCodeException("This promo code is not valid for the selected ticket tier.");
        }

        return $promoCode;
    }
}
