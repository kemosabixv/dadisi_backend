<?php

namespace App\Exceptions;

/**
 * UserException
 *
 * Exception for user-related operations including profile
 * management and account actions.
 *
 * @package App\Exceptions
 */
class UserException extends \Exception
{
    /**
     * User not found
     *
     * @param string|int $identifier
     * @return static
     */
    public static function notFound(string|int $identifier = ''): static
    {
        $message = $identifier ? "User not found: {$identifier}" : 'User not found';
        return new static($message, 404);
    }

    /**
     * Profile not found
     *
     * @return static
     */
    public static function profileNotFound(): static
    {
        return new static('Please complete your profile first.', 400);
    }

    /**
     * Profile is private
     *
     * @return static
     */
    public static function profilePrivate(): static
    {
        return new static('This profile is private.', 403);
    }

    /**
     * Creation failed
     *
     * @param string $message
     * @return static
     */
    public static function creationFailed(string $message = 'User creation failed'): static
    {
        return new static($message, 422);
    }

    /**
     * Update failed
     *
     * @param string $message
     * @return static
     */
    public static function updateFailed(string $message = 'User update failed'): static
    {
        return new static($message, 422);
    }

    /**
     * Deletion failed
     *
     * @param string $message
     * @return static
     */
    public static function deletionFailed(string $message = 'User deletion failed'): static
    {
        return new static($message, 500);
    }

    /**
     * Invalid password
     *
     * @return static
     */
    public static function invalidPassword(): static
    {
        return new static('Invalid password', 422);
    }

    /**
     * Unauthorized action
     *
     * @param string $action
     * @return static
     */
    public static function unauthorized(string $action = 'perform this action'): static
    {
        return new static("Unauthorized to {$action}", 403);
    }

    /**
     * User already exists
     *
     * @param string $field
     * @return static
     */
    public static function alreadyExists(string $field = 'email'): static
    {
        return new static("User with this {$field} already exists", 422);
    }

    /**
     * Role assignment failed
     *
     * @param string $message
     * @return static
     */
    public static function roleAssignmentFailed(string $message = 'Role assignment failed'): static
    {
        return new static($message, 422);
    }

    /**
     * Restoration failed
     *
     * @param string $message
     * @return static
     */
    public static function restorationFailed(string $message = 'User restoration failed'): static
    {
        return new static($message, 500);
    }
}
