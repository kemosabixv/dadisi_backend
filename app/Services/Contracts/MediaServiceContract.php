<?php

namespace App\Services\Contracts;

use App\Models\Media;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * MediaServiceContract
 *
 * Defines the contract for media management operations including
 * file uploads, retrieval, and deletion with proper validation.
 *
 * @package App\Services\Contracts
 */
interface MediaServiceContract
{
    /**
     * Upload a new media file
     *
     * @param Authenticatable $user The user uploading the file
     * @param \Illuminate\Http\UploadedFile $file The uploaded file
     * @param array $metadata Optional metadata (attached_to, attached_to_id, temporary)
     * @return Media The created media record
     *
     * @throws \App\Exceptions\MediaException If upload fails or file type unsupported
     */
    public function uploadMedia(Authenticatable $user, \Illuminate\Http\UploadedFile $file, array $metadata = []): Media;

    /**
     * Get media by ID
     *
     * @param string|int $id Media ID
     * @return Media The media record
     *
     * @throws \App\Exceptions\MediaException If not found
     */
    public function getMedia(string|int $id): Media;

    /**
     * List media files for a user
     *
     * @param Authenticatable $user The user whose media to list
     * @param array $filters Filters (type, search)
     * @param int $perPage Items per page
     * @return LengthAwarePaginator Paginated media
     */
    public function listMedia(Authenticatable $user, array $filters = [], int $perPage = 30): LengthAwarePaginator;

    /**
     * Delete a media file
     *
     * @param Authenticatable $user The user performing deletion
     * @param Media $media The media to delete
     * @return bool Success status
     *
     * @throws \App\Exceptions\MediaException If unauthorized or deletion fails
     */
    public function deleteMedia(Authenticatable $user, Media $media): bool;

    /**
     * Get the public URL for a media file
     *
     * @param Media $media The media record
     * @return string The public URL
     */
    public function getMediaUrl(Media $media): string;

    /**
     * Validate file type and size
     *
     * @param \Illuminate\Http\UploadedFile $file The file to validate
     * @return array ['valid' => bool, 'type' => string|null, 'error' => string|null]
     */
    public function validateFile(\Illuminate\Http\UploadedFile $file): array;

    /**
     * Get allowed MIME types
     *
     * @return array Associative array of type => [mimes]
     */
    public function getAllowedMimeTypes(): array;

    /**
     * Get file size limits
     *
     * @return array Associative array of type => bytes
     */
    public function getFileSizeLimits(): array;
}
