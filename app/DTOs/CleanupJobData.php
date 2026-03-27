<?php

namespace App\DTOs;

/**
 * Generic Data Transfer Object for Cleanup Jobs
 * 
 * Reusable across all data destruction/cleanup job types
 */
class CleanupJobData
{
    public function __construct(
        public string $dataType,
        public int $retentionDays,
        public bool $dryRun = false,
        public bool $force = false,
        public ?string $filter = null,
    ) {}

    /**
     * Convert to array for queue serialization
     */
    public function toArray(): array
    {
        return [
            'dataType' => $this->dataType,
            'retentionDays' => $this->retentionDays,
            'dryRun' => $this->dryRun,
            'force' => $this->force,
            'filter' => $this->filter,
        ];
    }

    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            dataType: $data['dataType'],
            retentionDays: $data['retentionDays'] ?? 90,
            dryRun: $data['dryRun'] ?? false,
            force: $data['force'] ?? false,
            filter: $data['filter'] ?? null,
        );
    }

    /**
     * Get cutoff timestamp
     */
    public function getCutoffDate(): \DateTime
    {
        return now()->subDays($this->retentionDays);
    }

    /**
     * Get cutoff timestamp in minutes
     */
    public function getCutoffMinutes(): int
    {
        return $this->retentionDays * 24 * 60;
    }
}
