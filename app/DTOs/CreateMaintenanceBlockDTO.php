<?php

namespace App\DTOs;

/**
 * Create Maintenance Block DTO
 *
 * Data Transfer Object for maintenance/holiday/closure block creation.
 */
class CreateMaintenanceBlockDTO
{
    public function __construct(
        public int $lab_space_id,
        public string $block_type,
        public \DateTime $starts_at,
        public \DateTime $ends_at,
        public ?string $title = null,
        public ?string $reason = null,
        public int $created_by = 1,
        public bool $recurring = false,
        public ?string $recurrence_rule = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            lab_space_id: (int) $data['lab_space_id'],
            block_type: $data['block_type'],
            starts_at: new \DateTime($data['starts_at']),
            ends_at: new \DateTime($data['ends_at']),
            title: $data['title'] ?? null,
            reason: $data['reason'] ?? null,
            created_by: isset($data['created_by']) ? (int) $data['created_by'] : 1,
            recurring: $data['recurring'] ?? false,
            recurrence_rule: $data['recurrence_rule'] ?? null,
        );
    }

    /**
     * Convert DTO to array for model creation
     */
    public function toArray(): array
    {
        return [
            'lab_space_id' => $this->lab_space_id,
            'block_type' => $this->block_type,
            'starts_at' => $this->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $this->ends_at->format('Y-m-d H:i:s'),
            'title' => $this->title,
            'reason' => $this->reason,
            'created_by' => $this->created_by,
            'recurring' => $this->recurring,
            'recurrence_rule' => $this->recurrence_rule,
        ];
    }
}
