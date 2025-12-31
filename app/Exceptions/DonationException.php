<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * DonationException
 *
 * Exception for donation-related operations
 */
class DonationException extends Exception
{
    /**
     * @var int
     */
    public int $statusCode;

    /**
     * DonationException constructor.
     */
    public function __construct(string $message = "", int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    /**
     * Donation not found
     */
    public static function notFound(string|int $identifier): static
    {
        return new static("Donation not found: {$identifier}", Response::HTTP_NOT_FOUND);
    }

    /**
     * Donation creation failed
     */
    public static function creationFailed(string $message = 'Donation creation failed'): static
    {
        return new static($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Invalid amount
     */
    public static function invalidAmount(float $amount): static
    {
        return new static("Invalid donation amount: {$amount}", Response::HTTP_BAD_REQUEST);
    }

    /**
     * Campaign not active
     */
    public static function campaignNotActive(int|string $campaignId): static
    {
        return new static("Campaign is not accepting donations: {$campaignId}", Response::HTTP_BAD_REQUEST);
    }

    /**
     * Campaign goal reached
     */
    public static function campaignGoalReached(int|string $campaignId): static
    {
        return new static("Campaign has reached its goal: {$campaignId}", Response::HTTP_BAD_REQUEST);
    }

    /**
     * Payment failed
     */
    public static function paymentFailed(string $reason): static
    {
        return new static("Payment failed: {$reason}", Response::HTTP_BAD_REQUEST);
    }

    /**
     * Verification failed
     */
    public static function verificationFailed(string $message = 'Donation verification failed'): static
    {
        return new static($message, Response::HTTP_BAD_REQUEST);
    }

    /**
     * Report generation failed
     */
    public static function reportGenerationFailed(string $message = 'Report generation failed'): static
    {
        return new static($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Receipt generation failed
     */
    public static function receiptFailed(string $message = 'Receipt generation failed'): static
    {
        return new static($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Unauthorized action
     */
    public static function unauthorized(string $action = 'perform this action'): static
    {
        return new static("Unauthorized to {$action} on this donation", Response::HTTP_FORBIDDEN);
    }

    /**
     * Only pending donations can be cancelled
     */
    public static function onlyPendingCanBeCancelled(): static
    {
        return new static("Only pending donations can be cancelled", Response::HTTP_BAD_REQUEST);
    }

    /**
     * Donation is already paid
     */
    public static function alreadyPaid(int|string $donationId): static
    {
        return new static("Donation is already paid: {$donationId}", Response::HTTP_BAD_REQUEST);
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
