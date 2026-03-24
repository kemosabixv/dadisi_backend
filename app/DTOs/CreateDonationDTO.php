<?php

namespace App\DTOs;

/**
 * Create Donation DTO
 *
 * Data Transfer Object for donation creation operations.
 * Carries validated donation data from request to service layer.
 */
class CreateDonationDTO
{
    public function __construct(
        public float $amount,
        public int $county_id,
        public string $currency = 'KES',
        public ?int $user_id = null,
        public ?string $donor_name = null,
        public ?string $donor_email = null,
        public ?string $donor_phone = null,
        public ?int $campaign_id = null,
        public ?string $reference = null,
        public ?string $notes = null,
        public string $status = 'pending',
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            county_id: (int) $data['county_id'],
            currency: $data['currency'] ?? 'KES',
            user_id: $data['user_id'] ?? null,
            donor_name: $data['donor_name'] ?? null,
            donor_email: $data['donor_email'] ?? null,
            donor_phone: $data['donor_phone'] ?? null,
            campaign_id: $data['campaign_id'] ?? null,
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
            status: $data['status'] ?? 'pending',
        );
    }

    /**
     * Convert DTO to array for model creation
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'county_id' => $this->county_id,
            'currency' => $this->currency,
            'user_id' => $this->user_id,
            'donor_name' => $this->donor_name,
            'donor_email' => $this->donor_email,
            'donor_phone' => $this->donor_phone,
            'campaign_id' => $this->campaign_id,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'status' => $this->status,
        ];
    }
}
