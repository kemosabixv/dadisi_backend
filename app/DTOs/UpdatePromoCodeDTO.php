<?php

namespace App\DTOs;

/**
 * Update Promo Code DTO
 *
 * Data Transfer Object for promo code update operations.
 */
class UpdatePromoCodeDTO
{
    public function __construct(
        public ?string $code = null,
        public ?string $discount_type = null,
        public ?float $discount_value = null,
        public ?int $usage_limit = null,
        public ?bool $is_active = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'] ?? null,
            discount_type: $data['discount_type'] ?? null,
            discount_value: isset($data['discount_value']) ? (float) $data['discount_value'] : null,
            usage_limit: isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            is_active: isset($data['is_active']) ? (bool) $data['is_active'] : null,
        );
    }

    /**
     * Convert DTO to array, filtering out null values
     */
    public function toArray(): array
    {
        return array_filter([
            'code' => $this->code,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'usage_limit' => $this->usage_limit,
            'is_active' => $this->is_active,
        ], fn ($value) => $value !== null);
    }
}
