<?php

namespace App\DTOs;

use Illuminate\Http\Request;

/**
 * EventRegistrationDTO
 *
 * Data Transfer Object for event registration with type-safe properties.
 */
class EventRegistrationDTO
{
    public function __construct(
        public string $user_id,
        public string $event_id,
        public ?array $additional_data = null,
    ) {}

    /**
     * Create DTO from request
     *
     * @param Request $request The incoming request
     * @return self
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            user_id: $request->input('user_id'),
            event_id: $request->input('event_id'),
            additional_data: $request->input('additional_data'),
        );
    }

    /**
     * Convert to array for storage
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'event_id' => $this->event_id,
            'additional_data' => $this->additional_data,
        ];
    }
}
