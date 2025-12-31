<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EventException extends Exception
{
    public int $statusCode;

    public function __construct(
        string $message = 'Event operation failed',
        int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public static function creationFailed(string $error): self
    {
        return new self("Failed to create event: {$error}", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function updateFailed(string $error): self
    {
        return new self("Failed to update event: {$error}", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function deletionFailed(string $error): self
    {
        return new self("Failed to delete event: {$error}", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function restorationFailed(string $error): self
    {
        return new self("Failed to restore event: {$error}", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function notFound(string $id): self
    {
        return new self("Event not found with ID: {$id}", Response::HTTP_NOT_FOUND);
    }

    public static function notFoundBySlug(string $slug): self
    {
        return new self("Event not found with slug: {$slug}", Response::HTTP_NOT_FOUND);
    }

    public static function capacityExceeded(): self
    {
        return new self("Event capacity exceeded.", Response::HTTP_CONFLICT);
    }

    public static function registrationClosed(): self
    {
        return new self("Registration for this event is closed.", Response::HTTP_FORBIDDEN);
    }

    public static function alreadyRegistered(): self
    {
        return new self("You are already registered for this event.", Response::HTTP_CONFLICT);
    }

    public static function registrationFailed(string $error): self
    {
        return new self("Event registration failed: {$error}", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function registrationNotFound(): self
    {
        return new self("Registration not found.", Response::HTTP_NOT_FOUND);
    }

    public static function cancellationFailed(string $error): self
    {
        return new self("Failed to cancel registration: {$error}", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function bulkOperationLimitExceeded(int $limit): self
    {
        return new self("Bulk operation limit exceeded. Maximum {$limit} items allowed.", Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public static function bulkRegistrationFailed(string $error): self
    {
        return new self("Bulk registration failed: {$error}", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function bulkCancellationFailed(string $error): self
    {
        return new self("Bulk cancellation failed: {$error}", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @return static
     */
    public static function invalidPriority(): static
    {
        return new static("Invalid priority. Must be between 1 and 10.", Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @param string $error
     * @return static
     */
    public static function featuringFailed(string $error): static
    {
        return new static("Failed to feature event: {$error}", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @param string $error
     * @return static
     */
    public static function unfeaturingFailed(string $error): static
    {
        return new static("Failed to unfeature event: {$error}", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @param string $error
     * @return static
     */
    public static function priorityUpdateFailed(string $error): static
    {
        return new static("Failed to update feature priority: {$error}", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @return static
     */
    public static function capacityBelowAttendeeCount(): static
    {
        return new static("Cannot reduce capacity below current attendee count.", Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @param string $error
     * @return static
     */
    public static function capacityUpdateFailed(string $error): static
    {
        return new static("Failed to update event capacity: {$error}", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }
}
