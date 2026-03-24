<?php

namespace App\DTOs;

/**
 * API Response DTO
 *
 * Standardized data structure for API responses across the application.
 */
readonly class ApiResponseDTO
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public mixed $data = null,
    ) {}

    /**
     * Create a success response
     */
    public static function success(mixed $data = null, ?string $message = null): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
        );
    }

    /**
     * Create a failure response
     */
    public static function failure(?string $message = null, mixed $data = null): self
    {
        return new self(
            success: false,
            message: $message,
            data: $data,
        );
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        $response = ['success' => $this->success];

        if ($this->message !== null) {
            $response['message'] = $this->message;
        }

        if ($this->data !== null) {
            $response['data'] = $this->data;
        }

        return $response;
    }
}
