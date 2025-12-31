<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class EventTicketException extends Exception
{
    public int $statusCode;

    public function __construct(string $message, int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public static function creationFailed(string $message): self
    {
        return new self("Ticket creation failed: $message", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function updateFailed(string $message): self
    {
        return new self("Ticket update failed: $message", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function notFound(int $id): self
    {
        return new self("Ticket with ID $id not found", Response::HTTP_NOT_FOUND);
    }

    public static function closureFailed(string $message): self
    {
        return new self("Ticket deletion failed: $message", Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }
}
