<?php

namespace App\Exceptions;

/**
 * PromoCodeException
 *
 * Exception for promo code operations
 */
class PromoCodeException extends \Exception
{
    public static function creationFailed(string $message = 'Promo code creation failed'): static
    {
        return new static($message);
    }

    public static function updateFailed(string $message = 'Promo code update failed'): static
    {
        return new static($message);
    }

    public static function codeAlreadyExists(): static
    {
        return new static('Promo code already exists');
    }

    public static function codeNotFound(string $code): static
    {
        return new static("Promo code not found: {$code}");
    }

    public static function codeInactive(): static
    {
        return new static('Promo code is inactive');
    }

    public static function codeNotYetValid(): static
    {
        return new static('Promo code is not yet valid');
    }

    public static function codeExpired(): static
    {
        return new static('Promo code has expired');
    }

    public static function usageLimitReached(): static
    {
        return new static('Promo code usage limit has been reached');
    }

    public static function redemptionFailed(string $message = 'Promo code redemption failed'): static
    {
        return new static($message);
    }

    public static function deactivationFailed(string $message = 'Promo code deactivation failed'): static
    {
        return new static($message);
    }
}
