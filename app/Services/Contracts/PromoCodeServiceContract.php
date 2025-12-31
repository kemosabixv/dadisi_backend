<?php

namespace App\Services\Contracts;

use App\Models\PromoCode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * PromoCodeServiceContract
 *
 * Contract for promo code management
 */
interface PromoCodeServiceContract
{
    /**
     * Create a new promo code
     *
     * @param Authenticatable $creator
     * @param array $data
     * @return PromoCode
     */
    public function createPromoCode(Authenticatable $creator, array $data): PromoCode;

    /**
     * Update a promo code
     *
     * @param Authenticatable $actor
     * @param PromoCode $promoCode
     * @param array $data
     * @return PromoCode
     */
    public function updatePromoCode(Authenticatable $actor, PromoCode $promoCode, array $data): PromoCode;

    /**
     * Validate and redeem a promo code
     *
     * @param string $code
     * @param Authenticatable $user
     * @return PromoCode
     */
    public function redeemPromoCode(string $code, Authenticatable $user): PromoCode;

    /**
     * Deactivate a promo code
     *
     * @param Authenticatable $actor
     * @param PromoCode $promoCode
     * @return PromoCode
     */
    public function deactivatePromoCode(Authenticatable $actor, PromoCode $promoCode): PromoCode;

    /**
     * List promo codes
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function listPromoCodes(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get promo code statistics
     *
     * @param PromoCode $promoCode
     * @return array
     */
    public function getStatistics(PromoCode $promoCode): array;

    /**
     * Generate a promo code with auto-generated code
     *
     * @param Authenticatable $creator
     * @param array $data
     * @return PromoCode
     */
    public function generatePromoCode(Authenticatable $creator, array $data): PromoCode;

    /**
     * Validate a promo code
     *
     * @param string $code
     * @return PromoCode
     */
    public function validateCode(string $code): PromoCode;

    /**
     * Record usage of a promo code
     *
     * @param PromoCode $promoCode
     * @return PromoCode
     */
    public function recordUsage(PromoCode $promoCode): PromoCode;

    /**
     * Activate a promo code
     *
     * @param Authenticatable $actor
     * @param PromoCode $promoCode
     * @return PromoCode
     */
    public function activateCode(Authenticatable $actor, PromoCode $promoCode): PromoCode;

    /**
     * Deactivate a promo code (alias for deactivatePromoCode)
     *
     * @param Authenticatable $actor
     * @param PromoCode $promoCode
     * @return PromoCode
     */
    public function deactivateCode(Authenticatable $actor, PromoCode $promoCode): PromoCode;

    /**
     * Get a promo code by ID or code string
     *
     * @param int|string $idOrCode
     * @return PromoCode
     */
    public function getPromoCode(int|string $idOrCode): PromoCode;
}
