<?php

namespace App\Services\Media;

use App\Exceptions\MediaException;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\UserDataRetentionSetting;
use App\Services\Contracts\MediaServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * MediaService
 *
 * Handles media file management including uploads, retrieval, deletion,
 * and validation with comprehensive audit logging.
 *
 * @package App\Services\Media
 */
class MediaService implements MediaServiceContract
{
    /**
     * File size limits (in bytes)
     */
    private const FILE_LIMITS = [
        'image' => 5 * 1024 * 1024,      // 5 MB
        'audio' => 10 * 1024 * 1024,     // 10 MB
        'video' => 50 * 1024 * 1024,     // 50 MB
        'pdf' => 30 * 1024 * 1024,       // 30 MB
        'gif' => 5 * 1024 * 1024,        // 5 MB
    ];

    /**
     * Allowed MIME types by category
     */
    private const ALLOWED_MIMES = [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
        'audio' => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm'],
        'video' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
        'pdf' => ['application/pdf'],
        'gif' => ['image/gif'],
    ];

    /**
     * Upload a new media file
     *
     * @param Authenticatable $user The user uploading the file
     * @param UploadedFile $file The uploaded file
     * @param array $metadata Optional metadata (attached_to, attached_to_id, temporary)
     * @return Media The created media record
     *
     * @throws MediaException If upload fails or file type unsupported
     */
    public function uploadMedia(Authenticatable $user, UploadedFile $file, array $metadata = []): Media
    {
        try {
            DB::beginTransaction();

            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                throw MediaException::validationFailed($validation['error']);
            }

            $type = $validation['type'];
            $mimeType = $file->getMimeType();

            // Store file with unique name
            $directory = 'media/' . now()->format('Y-m');
            $originalName = $file->getClientOriginalName();
            $uniqueName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) 
                . '-' . Str::random(8) 
                . '.' . $file->getClientOriginalExtension();

            $path = Storage::disk('public')->putFileAs($directory, $file, $uniqueName);

            if (!$path) {
                throw MediaException::storageError('Failed to store file');
            }

            // Determine temporary expiration if flag set
            $temporaryUntil = null;
            if (!empty($metadata['temporary'])) {
                $minutes = UserDataRetentionSetting::getRetentionMinutes('temporary_media');
                $temporaryUntil = now()->addMinutes($minutes);
            }

            $media = Media::create([
                'user_id' => $user->getAuthIdentifier(),
                'file_name' => $originalName,
                'file_path' => '/' . $path,
                'type' => $type,
                'mime_type' => $mimeType,
                'file_size' => $file->getSize(),
                'is_public' => false,
                'attached_to' => $metadata['attached_to'] ?? null,
                'attached_to_id' => $metadata['attached_to_id'] ?? null,
                'temporary_until' => $temporaryUntil,
            ]);

            // Audit log
            $this->logAudit($user->getAuthIdentifier(), 'create', $media, null, [
                'file_name' => $media->file_name,
                'type' => $media->type,
                'file_size' => $media->file_size,
            ], "Uploaded media: {$media->file_name}");

            Log::info('Media uploaded', [
                'media_id' => $media->id,
                'user_id' => $user->getAuthIdentifier(),
                'file_name' => $media->file_name,
            ]);

            DB::commit();

            return $media;
        } catch (MediaException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Media upload failed', [
                'user_id' => $user->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);
            throw MediaException::uploadFailed($e->getMessage());
        }
    }

    /**
     * Get media by ID
     *
     * @param string|int $id Media ID
     * @return Media The media record
     *
     * @throws MediaException If not found
     */
    public function getMedia(string|int $id): Media
    {
        try {
            return Media::findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw MediaException::notFound($id);
        }
    }

    /**
     * List media files for a user
     *
     * @param Authenticatable $user The user whose media to list
     * @param array $filters Filters (type, search)
     * @param int $perPage Items per page
     * @return LengthAwarePaginator Paginated media
     */
    public function listMedia(Authenticatable $user, array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        $query = Media::where('user_id', $user->getAuthIdentifier());

        // Filter by type
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Search by filename
        if (!empty($filters['search'])) {
            $query->where('file_name', 'like', "%{$filters['search']}%");
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Delete a media file
     *
     * @param Authenticatable $user The user performing deletion
     * @param Media $media The media to delete
     * @return bool Success status
     *
     * @throws MediaException If unauthorized or deletion fails
     */
    public function deleteMedia(Authenticatable $user, Media $media): bool
    {
        try {
            // Ownership check
            if ($media->user_id !== $user->getAuthIdentifier()) {
                throw MediaException::unauthorized('delete');
            }

            DB::beginTransaction();

            // Store info for audit before deletion
            $mediaInfo = [
                'file_name' => $media->file_name,
                'type' => $media->type,
            ];

            // Delete file from storage
            if ($media->file_path) {
                Storage::disk('public')->delete(ltrim($media->file_path, '/'));
            }

            $this->logAudit(
                $user->getAuthIdentifier(),
                'delete',
                $media,
                $mediaInfo,
                null,
                "Deleted media: {$media->file_name}"
            );

            // Delete database record
            $media->forceDelete();

            Log::info('Media deleted', [
                'media_id' => $media->id,
                'user_id' => $user->getAuthIdentifier(),
            ]);

            DB::commit();

            return true;
        } catch (MediaException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Media deletion failed', [
                'media_id' => $media->id,
                'user_id' => $user->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);
            throw MediaException::deletionFailed($e->getMessage());
        }
    }

    /**
     * Get the public URL for a media file
     *
     * @param Media $media The media record
     * @return string The public URL
     */
    public function getMediaUrl(Media $media): string
    {
        return url('/storage' . $media->file_path);
    }

    /**
     * Validate file type and size
     *
     * @param UploadedFile $file The file to validate
     * @return array ['valid' => bool, 'type' => string|null, 'error' => string|null]
     */
    public function validateFile(UploadedFile $file): array
    {
        $mimeType = $file->getMimeType();

        // Determine file type
        $type = $this->getFileType($mimeType);
        if (!$type) {
            return [
                'valid' => false,
                'type' => null,
                'error' => "Unsupported file type: {$mimeType}",
            ];
        }

        // Check file size
        $maxSize = self::FILE_LIMITS[$type] ?? 5 * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            $maxSizeMB = $maxSize / (1024 * 1024);
            return [
                'valid' => false,
                'type' => $type,
                'error' => "File exceeds maximum size of {$maxSizeMB}MB",
            ];
        }

        return [
            'valid' => true,
            'type' => $type,
            'error' => null,
        ];
    }

    /**
     * Get allowed MIME types
     *
     * @return array Associative array of type => [mimes]
     */
    public function getAllowedMimeTypes(): array
    {
        return self::ALLOWED_MIMES;
    }

    /**
     * Get file size limits
     *
     * @return array Associative array of type => bytes
     */
    public function getFileSizeLimits(): array
    {
        return self::FILE_LIMITS;
    }

    /**
     * Determine file type from MIME type
     *
     * @param string $mimeType
     * @return string|null
     */
    private function getFileType(string $mimeType): ?string
    {
        foreach (self::ALLOWED_MIMES as $type => $mimes) {
            if (in_array($mimeType, $mimes)) {
                return $type;
            }
        }
        return null;
    }

    /**
     * Log audit action
     *
     * @param int $userId
     * @param string $action
     * @param Media $media
     * @param array|null $oldValues
     * @param array|null $newValues
     * @param string|null $notes
     * @return void
     */
    private function logAudit(
        int $userId,
        string $action,
        Media $media,
        ?array $oldValues,
        ?array $newValues,
        ?string $notes = null
    ): void {
        try {
            AuditLog::create([
                'action' => $action,
                'model_type' => Media::class,
                'model_id' => $media->id,
                'user_id' => $userId,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'notes' => $notes,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create audit log for media', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
