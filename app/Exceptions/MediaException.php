<?php

namespace App\Exceptions;

/**
 * MediaException
 *
 * Exception for media-related operations including uploads,
 * validation failures, and ownership issues.
 *
 * @package App\Exceptions
 */
class MediaException extends \Exception
{
    /**
     * Media upload failed
     *
     * @param string $message
     * @return static
     */
    public static function uploadFailed(string $message = 'Media upload failed'): static
    {
        return new static($message, 422);
    }

    /**
     * Unsupported file type
     *
     * @param string $mimeType
     * @return static
     */
    public static function unsupportedFileType(string $mimeType = ''): static
    {
        $message = $mimeType 
            ? "Unsupported file type: {$mimeType}" 
            : 'Unsupported file type';
        return new static($message, 422);
    }

    /**
     * File size exceeded
     *
     * @param int $maxSizeMB Maximum size in MB
     * @return static
     */
    public static function fileSizeExceeded(int $maxSizeMB): static
    {
        return new static("File exceeds maximum size of {$maxSizeMB}MB", 422);
    }

    /**
     * Media not found
     *
     * @param string|int $id
     * @return static
     */
    public static function notFound(string|int $id = ''): static
    {
        $message = $id ? "Media not found: {$id}" : 'Media not found';
        return new static($message, 404);
    }

    /**
     * Unauthorized access
     *
     * @param string $action
     * @return static
     */
    public static function unauthorized(string $action = 'access'): static
    {
        return new static("Unauthorized to {$action} this media", 403);
    }

    /**
     * Deletion failed
     *
     * @param string $message
     * @return static
     */
    public static function deletionFailed(string $message = 'Media deletion failed'): static
    {
        return new static($message, 500);
    }

    /**
     * Storage error
     *
     * @param string $message
     * @return static
     */
    public static function storageError(string $message = 'Storage operation failed'): static
    {
        return new static($message, 500);
    }

    /**
     * Validation failed
     *
     * @param string $message
     * @return static
     */
    public static function validationFailed(string $message = 'Media validation failed'): static
    {
        return new static($message, 422);
    }
}
