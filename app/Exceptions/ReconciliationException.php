<?php

namespace App\Exceptions;

/**
 * ReconciliationException
 *
 * Exception for reconciliation operations
 */
class ReconciliationException extends \Exception
{
    public static function donationReconciliationFailed(string $message = 'Donation reconciliation failed'): static
    {
        return new static($message);
    }

    public static function paymentReconciliationFailed(string $message = 'Payment reconciliation failed'): static
    {
        return new static($message);
    }

    public static function eventReconciliationFailed(string $message = 'Event reconciliation failed'): static
    {
        return new static($message);
    }

    public static function reportGenerationFailed(string $message = 'Report generation failed'): static
    {
        return new static($message);
    }

    public static function flaggingFailed(string $message = 'Failed to flag discrepancy'): static
    {
        return new static($message);
    }
}
