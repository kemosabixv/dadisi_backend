<?php

namespace App\Exceptions;

/**
 * PostException
 *
 * Exception for post-related operations
 */
class PostException extends \Exception
{
    /**
     * @var int
     */
    public $statusCode;

    /**
     * PostException constructor.
     */
    public function __construct(string $message = "", int $statusCode = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }

    /**
     * Generic factory method for creating exceptions
     */
    public static function create(string $message, int $statusCode = 422): static
    {
        return new static($message, $statusCode);
    }

    /**
     * Post creation failed
     */
    public static function creationFailed(string $message = 'Post creation failed', int $statusCode = 422): static
    {
        return new static($message, $statusCode);
    }

    /**
     * Post update failed
     */
    public static function updateFailed(string $message = 'Post update failed', int $statusCode = 422): static
    {
        return new static($message, $statusCode);
    }

    /**
     * Post not found
     */
    public static function notFound(string $identifier): static
    {
        return new static("Post not found: {$identifier}", 404);
    }

    /**
     * Post already published
     */
    public static function alreadyPublished(): static
    {
        return new static('Post is already published', 400);
    }

    /**
     * Post publish failed
     */
    public static function publishFailed(string $message = 'Post publish failed', int $statusCode = 422): static
    {
        return new static($message, $statusCode);
    }

    /**
     * Post unpublish failed
     */
    public static function unpublishFailed(string $message = 'Post unpublish failed', int $statusCode = 422): static
    {
        return new static($message, $statusCode);
    }

    /**
     * Post deletion failed
     */
    public static function deletionFailed(string $message = 'Post deletion failed', int $statusCode = 422): static
    {
        return new static($message, $statusCode);
    }

    /**
     * Post restoration failed
     */
    public static function restorationFailed(string $message = 'Post restoration failed', int $statusCode = 422): static
    {
        return new static($message, $statusCode);
    }

    /**
     * Post not deleted
     */
    public static function notDeleted(): static
    {
        return new static('Post is not deleted', 400);
    }

    /**
     * Unauthorized action
     */
    public static function unauthorized(string $message = 'Unauthorized action'): static
    {
        return new static($message, 403);
    }
}
