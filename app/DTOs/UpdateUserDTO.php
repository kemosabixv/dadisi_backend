<?php

namespace App\DTOs;

use App\Http\Requests\Api\UpdateUserRequest;

/**
 * Update User DTO
 *
 * Data Transfer Object for user update operations.
 * All fields are optional as this is for partial updates.
 */
class UpdateUserDTO
{
    public function __construct(
        public ?string $email = null,
        public ?string $username = null,
    ) {}

    /**
     * Create DTO from FormRequest
     *
     * @param UpdateUserRequest $request The validated request
     * @return self
     */
    public static function fromRequest(UpdateUserRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            email: $validated['email'] ?? null,
            username: $validated['username'] ?? null,
        );
    }

    /**
     * Convert DTO to array for model update
     *
     * Only includes fields that are set (not null)
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_filter([
            'email' => $this->email,
            'username' => $this->username,
        ], fn($value) => $value !== null);
    }
}
