<?php

namespace App\DTOs;

/**
 * Update Lab Booking DTO
 *
 * Data Transfer Object for lab booking update operations.
 */
class UpdateLabBookingDTO
{
    public function __construct(
        public ?\DateTime $starts_at = null,
        public ?\DateTime $ends_at = null,
        public ?string $title = null,
        public ?string $purpose = null,
        public ?string $status = null,
        public ?string $admin_notes = null,
        public ?string $rejection_reason = null,
        public ?string $payment_method = null,
        public ?float $paid_amount = null,
        public ?float $total_price = null,
        public ?string $reference = null,
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
            purpose: $data['purpose'] ?? $data['description'] ?? null,
            status: $data['status'] ?? null,
            admin_notes: $data['admin_notes'] ?? $data['notes'] ?? null,
            rejection_reason: $data['rejection_reason'] ?? null,
            payment_method: $data['payment_method'] ?? null,
            paid_amount: isset($data['paid_amount']) ? (float) $data['paid_amount'] : null,
            total_price: isset($data['total_price']) ? (float) $data['total_price'] : null,
            reference: $data['reference'] ?? $data['booking_reference'] ?? null,
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
            'purpose' => $this->purpose,
            'status' => $this->status,
            'admin_notes' => $this->admin_notes,
            'rejection_reason' => $this->rejection_reason,
            'payment_method' => $this->payment_method,
            'paid_amount' => $this->paid_amount,
            'total_price' => $this->total_price,
            'booking_reference' => $this->reference,
        ], fn ($value) => $value !== null);
    }
}
