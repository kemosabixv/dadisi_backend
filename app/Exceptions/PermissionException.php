<?php

namespace App\Exceptions;

/**
 * PermissionException
 *
 * Exception for permission operations
 */
class PermissionException extends \Exception
{
    public static function creationFailed(string $message = 'Permission creation failed'): static
    {
        return new static($message);
    }

    public static function updateFailed(string $message = 'Permission update failed'): static
    {
        return new static($message);
    }

    public static function deletionFailed(string $message = 'Permission deletion failed'): static
    {
        return new static($message);
    }

    public static function grantFailed(string $message = 'Permission grant failed'): static
    {
        return new static($message);
    }

    public static function revokeFailed(string $message = 'Permission revoke failed'): static
    {
        return new static($message);
    }

    public static function roleNotFound(string $roleName): static
    {
        return new static("Role not found: {$roleName}");
    }
}
