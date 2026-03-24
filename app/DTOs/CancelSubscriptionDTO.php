<?php

namespace App\DTOs;

/**
 * Cancel Subscription DTO
 *
 * Data Transfer Object for subscription cancellation operations.
 */
class CancelSubscriptionDTO
{
    public function __construct(
        public ?string $reason = null,
        public bool $immediate = false,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            reason: $data['reason'] ?? null,
            immediate: $data['immediate'] ?? false,
        );
    }

    /**
     * Convert to array (filters null values)
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }

        if ($this->immediate !== false) {
            $data['immediate'] = $this->immediate;
        }

        return $data;
    }
}
