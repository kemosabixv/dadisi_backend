<?php

namespace App\DTOs;

/**
 * Create Lab Booking DTO
 *
 * Data Transfer Object for lab booking creation operations.
 */
class CreateLabBookingDTO
{
    public function __construct(
        public int $lab_space_id,
        public \DateTime $starts_at,
        public \DateTime $ends_at,
        public ?int $user_id = null,
        public ?string $guest_name = null,
        public ?string $guest_email = null,
        public string $title = 'Lab Booking',
        public ?string $purpose = null,
        public string $slot_type = 'hourly',
        public string $status = 'pending',
        public ?string $admin_notes = null,
        public ?string $payment_method = null,
        public ?float $paid_amount = null,
        public ?float $total_price = null,
        public ?\DateTime $quota_hours = null,
        public ?string $reference = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            lab_space_id: (int) $data['lab_space_id'],
            starts_at: new \DateTime($data['starts_at']),
            ends_at: new \DateTime($data['ends_at']),
            user_id: isset($data['user_id']) ? (int) $data['user_id'] : null,
            guest_name: $data['guest_name'] ?? null,
            guest_email: $data['guest_email'] ?? null,
            title: $data['title'] ?? 'Lab Booking',
            purpose: $data['purpose'] ?? $data['description'] ?? null,
            slot_type: $data['slot_type'] ?? 'hourly',
            status: $data['status'] ?? 'pending',
            admin_notes: $data['admin_notes'] ?? $data['notes'] ?? null,
            payment_method: $data['payment_method'] ?? null,
            paid_amount: isset($data['paid_amount']) ? (float) $data['paid_amount'] : null,
            total_price: isset($data['total_price']) ? (float) $data['total_price'] : null,
            quota_hours: isset($data['quota_hours']) ? new \DateTime($data['quota_hours']) : null,
            reference: $data['reference'] ?? $data['booking_reference'] ?? null,
        );
    }

    /**
     * Convert DTO to array for model creation
     */
    public function toArray(): array
    {
        return [
            'lab_space_id' => $this->lab_space_id,
            'starts_at' => $this->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $this->ends_at->format('Y-m-d H:i:s'),
            'user_id' => $this->user_id,
            'guest_name' => $this->guest_name,
            'guest_email' => $this->guest_email,
            'title' => $this->title,
            'purpose' => $this->purpose,
            'slot_type' => $this->slot_type,
            'status' => $this->status,
            'admin_notes' => $this->admin_notes,
            'payment_method' => $this->payment_method,
            'paid_amount' => $this->paid_amount,
            'total_price' => $this->total_price,
            'quota_hours' => $this->quota_hours?->format('Y-m-d H:i:s'),
            'booking_reference' => $this->reference,
        ];
    }
}
