<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Exception for authorization failures
 */
class UnauthorizedException extends Exception
{
    /**
     * @var string Resource or action
     */
    protected $resource;

    /**
     * Create a new UnauthorizedException instance
     *
     * @param string $message Error message
     * @param string|null $resource Resource being accessed
     * @param int $code Exception code
     */
    public function __construct(
        string $message = 'Unauthorized',
        ?string $resource = null,
        int $code = 0
    ) {
        parent::__construct($message, $code);
        $this->resource = $resource;
    }

    /**
     * Get the resource
     */
    public function getResource(): ?string
    {
        return $this->resource;
    }

    /**
     * Render the exception into an HTTP response
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => 'UNAUTHORIZED',
        ], Response::HTTP_FORBIDDEN);
    }
}
