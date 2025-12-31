<?php

namespace App\Exceptions;

/**
 * UserDataRetentionException
 *
 * Exception for user data retention operations
 */
class UserDataRetentionException extends \Exception
{
    public static function markingFailed(string $message = 'Failed to mark user for deletion'): static
    {
        return new static($message);
    }

    public static function alreadyScheduled(): static
    {
        return new static('User deletion is already scheduled');
    }

    public static function schedulingFailed(string $message = 'Failed to schedule user deletion'): static
    {
        return new static($message);
    }

    public static function notScheduled(): static
    {
        return new static('User deletion is not scheduled');
    }

    public static function cancellationFailed(string $message = 'Failed to cancel user deletion'): static
    {
        return new static($message);
    }

    public static function archivingFailed(string $message = 'Failed to archive user data'): static
    {
        return new static($message);
    }

    public static function anonymizationFailed(string $message = 'Failed to anonymize user data'): static
    {
        return new static($message);
    }
}
