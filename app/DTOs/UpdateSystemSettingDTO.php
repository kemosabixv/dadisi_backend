<?php

namespace App\DTOs;

/**
 * Update System Setting DTO
 *
 * Data Transfer Object for system settings update operations.
 */
class UpdateSystemSettingDTO
{
    /**
     * @param array $settings Key-value pairs of settings to update
     */
    public function __construct(
        public array $settings,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        // Remove token and other request metadata
        unset($data['_token']);
        
        return new self(
            settings: $data,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return $this->settings;
    }
}
