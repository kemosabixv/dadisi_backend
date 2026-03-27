<?php

namespace App\DTOs;

/**
 * Data Transfer Object for Webhook Events Cleanup Job
 * 
 * Carries configuration from command to job execution
 */
class CleanupWebhookEventsJobData
{
    public function __construct(
        public int $retentionDays = 90,
        public bool $dryRun = false,
        public string $dataType = 'webhook_events',
    ) {}

    /**
     * Convert to array for queue serialization
     */
    public function toArray(): array
    {
        return [
            'retentionDays' => $this->retentionDays,
            'dryRun' => $this->dryRun,
            'dataType' => $this->dataType,
        ];
    }

    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            retentionDays: $data['retentionDays'] ?? 90,
            dryRun: $data['dryRun'] ?? false,
            dataType: $data['dataType'] ?? 'webhook_events',
        );
    }
}
