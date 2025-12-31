<?php

namespace App\Exceptions;

/**
 * PaymentException
 *
 * Exception for payment-related operations
 */
class PaymentException extends \Exception
{
    /**
     * Payment creation failed
     *
     * @param string $message
     * @return static
     */
    public static function creationFailed(string $message = 'Payment creation failed'): static
    {
        return new static($message);
    }

    /**
     * Payment processing failed
     *
     * @param string $message
     * @return static
     */
    public static function processingFailed(string $message = 'Payment processing failed'): static
    {
        return new static($message);
    }

    /**
     * Payment verification failed
     *
     * @param string|null $transactionId
     * @param string $message
     * @return static
     */
    public static function verificationFailed(?string $transactionId = null, string $message = 'Payment verification failed'): static
    {
        $error = $transactionId ? "Payment verification failed for [{$transactionId}]: " . $message : $message;
        return new static($error);
    }

    /**
     * Payment is in an invalid state for the requested operation 
     * 
     * @param string $message
     * @return static
     */
    public static function invalidState(string $message): static
    {
        return new static($message);
    }

    /**
     * Payment confirmation failed
     *
     * @param string $message
     * @return static
     */
    public static function confirmationFailed(string $message = 'Payment confirmation failed'): static
    {
        return new static($message);
    }

    /**
     * Cannot confirm payment
     *
     * @param string $message
     * @return static
     */
    public static function cannotConfirm(string $message = 'Payment cannot be confirmed'): static
    {
        return new static($message);
    }

    /**
     * Cannot refund payment
     *
     * @param string $message
     * @return static
     */
    public static function cannotRefund(string $message = 'Payment cannot be refunded'): static
    {
        return new static($message);
    }

    /**
     * Payment refund failed
     *
     * @param string $message
     * @return static
     */
    public static function refundFailed(string $message = 'Payment refund failed'): static
    {
        return new static($message);
    }

    /**
     * Payment not found
     *
     * @param int|string $id
     * @return static
     */
    public static function notFound(int|string $id): static
    {
        return new static("Payment not found: {$id}");
    }

    /**
     * Payment reconciliation failed
     *
     * @param string $message
     * @return static
     */
    public static function reconciliationFailed(string $message = 'Payment reconciliation failed'): static
    {
        return new static($message);
    }
}
