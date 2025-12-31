<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Exception for business logic conflicts (duplicate, invalid state, etc.)
 */
class BusinessLogicException extends Exception
{
    /**
     * @var string Error code for client-side handling
     */
    protected $errorCode;

    /**
     * @var array Additional context
     */
    protected $context = [];

    /**
     * Create a new BusinessLogicException instance
     *
     * @param string $message Error message
     * @param string|null $errorCode Machine-readable error code
     * @param array $context Additional context
     * @param int $code Exception code
     */
    public function __construct(
        string $message = 'Business logic constraint violated',
        ?string $errorCode = null,
        array $context = [],
        int $code = 0
    ) {
        parent::__construct($message, $code);
        $this->errorCode = $errorCode ?? 'BUSINESS_LOGIC_ERROR';
        $this->context = $context;
    }

    /**
     * Get error code
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Render the exception into an HTTP response
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'context' => $this->context,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
