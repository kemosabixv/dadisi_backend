<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;

class EventRegistrationException extends Exception
{
    protected $status = Response::HTTP_UNPROCESSABLE_ENTITY;

    public function __construct(
        string $message = 'Event registration failed',
        int $code = 0,
        Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->message,
            'type' => 'registration_failed',
        ], $this->status);
    }
}
