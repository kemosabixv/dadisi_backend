<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;

class EventCapacityExceededException extends Exception
{
    protected $status = Response::HTTP_CONFLICT;

    public function __construct(
        string $message = 'Event capacity has been exceeded',
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function eventAtCapacity(): self
    {
        return new self("This event is already at full capacity.");
    }

    public static function insufficientCapacity(int $remaining): self
    {
        return new self("Only {$remaining} spots remain for this event.");
    }

    public function render()
    {
        return response()->json([
            'message' => $this->message,
            'type' => 'capacity_exceeded',
        ], $this->status);
    }
}
