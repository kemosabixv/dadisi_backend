<?php

namespace App\DTOs;

use App\Http\Requests\Api\CreateUserRequest;

/**
 * Create User DTO
 *
 * Data Transfer Object for user creation operations.
 * Carries validated user data from request to service layer.
 */
class CreateUserDTO
{
    public function __construct(
        public string $email,
        public string $username,
        public string $password,
    ) {}

    /**
     * Create DTO from FormRequest
     *
     * @param CreateUserRequest $request The validated request
     * @return self
     */
    public static function fromRequest(CreateUserRequest $request): self
    {
        return new self(
            email: $request->validated('email'),
            username: $request->validated('username'),
            password: $request->validated('password'),
        );
    }

    /**
     * Convert DTO to array for model creation
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'username' => $this->username,
            'password' => bcrypt($this->password),
        ];
    }
}
