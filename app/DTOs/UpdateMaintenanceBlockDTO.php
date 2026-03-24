<?php

namespace App\DTOs;

/**
 * Update Maintenance Block DTO
 *
 * Data Transfer Object for maintenance/holiday/closure block updates.
 */
class UpdateMaintenanceBlockDTO
{
    public function __construct(
        public ?\DateTime $starts_at = null,
        public ?\DateTime $ends_at = null,
        public ?string $title = null,
        public ?string $reason = null,
        public ?bool $recurring = null,
        public ?string $recurrence_rule = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            starts_at: isset($data['starts_at']) ? new \DateTime($data['starts_at']) : null,
            ends_at: isset($data['ends_at']) ? new \DateTime($data['ends_at']) : null,
            title: $data['title'] ?? null,
            reason: $data['reason'] ?? null,
            recurring: isset($data['recurring']) ? (bool) $data['recurring'] : null,
            recurrence_rule: $data['recurrence_rule'] ?? null,
        );
    }

    /**
     * Convert DTO to array, filtering out null values
     */
    public function toArray(): array
    {
        return array_filter([
            'starts_at' => $this->starts_at?->format('Y-m-d H:i:s'),
            'ends_at' => $this->ends_at?->format('Y-m-d H:i:s'),
            'title' => $this->title,
            'reason' => $this->reason,
            'recurring' => $this->recurring,
            'recurrence_rule' => $this->recurrence_rule,
        ], fn ($value) => $value !== null);
    }
}
