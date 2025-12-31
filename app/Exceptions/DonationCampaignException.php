<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * DonationCampaignException
 *
 * Exception for donation campaign-related operations.
 */
class DonationCampaignException extends Exception
{
    /**
     * @var int
     */
    public int $statusCode;

    /**
     * DonationCampaignException constructor.
     */
    public function __construct(string $message = "", int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    /**
     * Campaign creation failed
     */
    public static function creationFailed(string $message = 'Campaign creation failed'): static
    {
        return new static($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Campaign update failed
     */
    public static function updateFailed(string $message = 'Campaign update failed'): static
    {
        return new static($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Campaign not found
     */
    public static function notFound(string|int $identifier): static
    {
        return new static("Campaign not found: {$identifier}", Response::HTTP_NOT_FOUND);
    }

    /**
     * Campaign deletion failed
     */
    public static function deletionFailed(string $message = 'Campaign deletion failed'): static
    {
        return new static($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Campaign restoration failed
     */
    public static function restorationFailed(string $message = 'Campaign restoration failed'): static
    {
        return new static($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Campaign publish failed
     */
    public static function publishFailed(string $message = 'Campaign publish failed'): static
    {
        return new static($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Campaign unpublish failed
     */
    public static function unpublishFailed(string $message = 'Campaign unpublish failed'): static
    {
        return new static($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Campaign complete failed
     */
    public static function completeFailed(string $message = 'Campaign complete failed'): static
    {
        return new static($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Unauthorized action
     */
    public static function unauthorized(string $action = 'perform this action'): static
    {
        return new static("Unauthorized to {$action} on this campaign", Response::HTTP_FORBIDDEN);
    }

    /**
     * Campaign is not active
     */
    public static function notActive(string $slug): static
    {
        return new static("Campaign '{$slug}' is not active", Response::HTTP_BAD_REQUEST);
    }

    /**
     * Campaign has not started
     */
    public static function notStarted(string $slug): static
    {
        return new static("Campaign '{$slug}' has not started yet", Response::HTTP_BAD_REQUEST);
    }

    /**
     * Campaign has ended
     */
    public static function ended(string $slug): static
    {
        return new static("Campaign '{$slug}' has ended", Response::HTTP_BAD_REQUEST);
    }

    /**
     * Minimum donation amount not met
     */
    public static function minimumAmountNotMet(string $currency, float $amount): static
    {
        return new static("Minimum donation amount of {$currency} " . number_format($amount, 2) . " not met", Response::HTTP_BAD_REQUEST);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render($request = null)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }
}
