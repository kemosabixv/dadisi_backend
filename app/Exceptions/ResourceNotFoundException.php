<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Exception for resource not found
 */
class ResourceNotFoundException extends Exception
{
    /**
     * @var string Resource type
     */
    protected $resourceType;

    /**
     * @var mixed Resource identifier
     */
    protected $resourceId;

    /**
     * Create a new ResourceNotFoundException instance
     *
     * @param string $message Error message
     * @param string|null $resourceType Type of resource (e.g., 'Payment', 'Subscription')
     * @param mixed $resourceId Identifier of the resource
     * @param int $code Exception code
     */
    public function __construct(
        string $message = 'Resource not found',
        ?string $resourceType = null,
        $resourceId = null,
        int $code = 0
    ) {
        parent::__construct($message, $code);
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
    }

    /**
     * Get resource type
     */
    public function getResourceType(): ?string
    {
        return $this->resourceType;
    }

    /**
     * Get resource ID
     */
    public function getResourceId()
    {
        return $this->resourceId;
    }

    /**
     * Render the exception into an HTTP response
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => 'NOT_FOUND',
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
        ], Response::HTTP_NOT_FOUND);
    }
}
