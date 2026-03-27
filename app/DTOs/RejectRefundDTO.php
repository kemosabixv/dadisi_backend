<?php

namespace App\DTOs;

/**
 * Reject Refund DTO
 *
 * Data Transfer Object for refund rejection operations.
 */
class RejectRefundDTO
{
    public function __construct(
        public ?string $admin_notes = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            admin_notes: $data['admin_notes'] ?? null,
        );
    }

    /**
     * Convert to array (filters null values)
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->admin_notes !== null) {
            $data['admin_notes'] = $this->admin_notes;
        }

        return $data;
    }
}
