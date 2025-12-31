<?php

namespace App\Exceptions;

/**
 * ForumException
 *
 * Exception for forum-related operations
 */
class ForumException extends \Exception
{
    /**
     * Generic forum exception
     *
     * @param string $message
     * @return static
     */
    public static function create(string $message): static
    {
        return new static($message);
    }

    /**
     * Thread creation failed
     *
     * @param string $message
     * @return static
     */
    public static function threadCreationFailed(string $message = 'Thread creation failed'): static
    {
        return new static($message);
    }

    /**
     * Thread update failed
     *
     * @param string $message
     * @return static
     */
    public static function threadUpdateFailed(string $message = 'Thread update failed'): static
    {
        return new static($message);
    }

    /**
     * Thread lock failed
     *
     * @param string $message
     * @return static
     */
    public static function threadLockFailed(string $message = 'Thread lock failed'): static
    {
        return new static($message);
    }

    /**
     * Thread unlock failed
     *
     * @param string $message
     * @return static
     */
    public static function threadUnlockFailed(string $message = 'Thread unlock failed'): static
    {
        return new static($message);
    }

    /**
     * Thread deletion failed
     *
     * @param string $message
     * @return static
     */
    public static function threadDeletionFailed(string $message = 'Thread deletion failed'): static
    {
        return new static($message);
    }

    /**
     * Thread not found
     *
     * @param string $identifier
     * @return static
     */
    public static function threadNotFound(string $identifier): static
    {
        return new static("Thread not found: {$identifier}");
    }

    /**
     * Thread is locked
     *
     * @return static
     */
    public static function threadLocked(): static
    {
        return new static('Thread is locked and cannot accept new posts');
    }

    /**
     * Post creation failed
     *
     * @param string $message
     * @return static
     */
    public static function postCreationFailed(string $message = 'Post creation failed'): static
    {
        return new static($message);
    }

    /**
     * Post update failed
     *
     * @param string $message
     * @return static
     */
    public static function postUpdateFailed(string $message = 'Post update failed'): static
    {
        return new static($message);
    }

    /**
     * Post deletion failed
     *
     * @param string $message
     * @return static
     */
    public static function postDeletionFailed(string $message = 'Post deletion failed'): static
    {
        return new static($message);
    }

    /**
     * Mark as read failed
     *
     * @param string $message
     * @return static
     */
    public static function markAsReadFailed(string $message = 'Mark as read failed'): static
    {
        return new static($message);
    }

    /**
     * Post not found
     *
     * @param int|string $identifier
     * @return static
     */
    public static function postNotFound(int|string $identifier): static
    {
        return new static("Post not found: {$identifier}");
    }
}
