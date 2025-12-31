<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Exception for validation failures in domain logic
 */
class ValidationException extends Exception
{
    /**
     * @var array Validation errors
     */
    protected $errors = [];

    /**
     * Create a new ValidationException instance
     *
     * @param string $message Error message
     * @param array $errors Validation errors
     * @param int $code Exception code
     */
    public function __construct(
        string $message = 'Validation failed',
        array $errors = [],
        int $code = 0
    ) {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Render the exception into an HTTP response
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
