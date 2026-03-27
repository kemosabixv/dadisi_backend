<?php

namespace App\DTOs;

/**
 * Update Renewal Preferences DTO
 *
 * Data Transfer Object for subscription renewal preferences updates.
 */
class UpdateRenewalPreferencesDTO
{
    public function __construct(
        public ?string $renewal_type = null,
        public ?bool $send_renewal_reminders = null,
        public ?int $reminder_days_before = null,
        public ?string $preferred_payment_method = null,
        public ?bool $auto_switch_to_free_on_expiry = null,
        public ?string $notes = null,
    ) {}

    /**
     * Create DTO from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            renewal_type: $data['renewal_type'] ?? null,
            send_renewal_reminders: isset($data['send_renewal_reminders']) ? (bool) $data['send_renewal_reminders'] : null,
            reminder_days_before: isset($data['reminder_days_before']) ? (int) $data['reminder_days_before'] : null,
            preferred_payment_method: $data['preferred_payment_method'] ?? null,
            auto_switch_to_free_on_expiry: isset($data['auto_switch_to_free_on_expiry']) ? (bool) $data['auto_switch_to_free_on_expiry'] : null,
            notes: $data['notes'] ?? null,
        );
    }

    /**
     * Convert to array (filters null values)
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->renewal_type !== null) {
            $data['renewal_type'] = $this->renewal_type;
        }

        if ($this->send_renewal_reminders !== null) {
            $data['send_renewal_reminders'] = $this->send_renewal_reminders;
        }

        if ($this->reminder_days_before !== null) {
            $data['reminder_days_before'] = $this->reminder_days_before;
        }

        if ($this->preferred_payment_method !== null) {
            $data['preferred_payment_method'] = $this->preferred_payment_method;
        }

        if ($this->auto_switch_to_free_on_expiry !== null) {
            $data['auto_switch_to_free_on_expiry'] = $this->auto_switch_to_free_on_expiry;
        }

        if ($this->notes !== null) {
            $data['notes'] = $this->notes;
        }

        return $data;
    }
}
