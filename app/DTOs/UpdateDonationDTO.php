<?php

namespace App\DTOs;

/**
 * Update Donation DTO
 *
 * Data Transfer Object for donation update operations.
 * Only allows updates to non-payment related fields.
 */
class UpdateDonationDTO
{
    public function __construct(
        public ?float $amount = null,
        public ?int $county_id = null,
        public ?int $campaign_id = null,
        public ?string $donor_name = null,
        public ?string $donor_email = null,
        public ?string $donor_phone = null,
        public ?string $notes = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            county_id: isset($data['county_id']) ? (int) $data['county_id'] : null,
            campaign_id: isset($data['campaign_id']) ? (int) $data['campaign_id'] : null,
            donor_name: $data['donor_name'] ?? null,
            donor_email: $data['donor_email'] ?? null,
            donor_phone: $data['donor_phone'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    /**
     * Convert DTO to array, filtering out null values
     */
    public function toArray(): array
    {
        return array_filter([
            'amount' => $this->amount,
            'county_id' => $this->county_id,
            'campaign_id' => $this->campaign_id,
            'donor_name' => $this->donor_name,
            'donor_email' => $this->donor_email,
            'donor_phone' => $this->donor_phone,
            'notes' => $this->notes,
        ], fn ($value) => $value !== null);
    }
}
