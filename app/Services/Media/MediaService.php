<?php

namespace App\Services\Media;

use App\Exceptions\MediaException;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\UserDataRetentionSetting;
use App\Notifications\SecureShareCreated;
use App\Notifications\StorageQuotaExceeded;
use App\Notifications\StorageUsageAlert;
use App\Services\Contracts\MediaServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\SubscriptionCoreService;
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
        'document' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',       // xlsx
            'text/csv',
        ],
    ];

    /**
     * @var SubscriptionCoreService
     */
    private $subscriptionService;

    public function __construct(SubscriptionCoreService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

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
            $validation = $this->validateFile($file, $user);
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
                'visibility' => 'private',
                'temporary_until' => $temporaryUntil,
            ]);

            // Audit log
            $this->logAudit($user->getAuthIdentifier(), 'create', $media, null, [
                'file_name' => $media->file_name,
                'type' => $media->type,
                'file_size' => $media->file_size,
            ], "Uploaded media: {$media->file_name}");

            // Notify user about storage thresholds
            $this->checkAndNotifyStorageThresholds($user, $media->file_size);

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
        // Check if user is an admin to decide whether to filter by user_id
        $isAdmin = \App\Support\AdminAccessResolver::canAccessAdmin($user);
        
        $query = Media::query();

        if (!$isAdmin) {
            // Non-admins only see their own media
            $query->where('user_id', $user->getAuthIdentifier());
        }

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
     * Rename a media file (display name only)
     *
     * @param Authenticatable $user
     * @param Media $media
     * @param string $newName
     * @return Media
     * @throws MediaException
     */
    public function renameMedia(Authenticatable $user, Media $media, string $newName): Media
    {
        $isAdmin = \App\Support\AdminAccessResolver::canAccessAdmin($user);
        if (!$isAdmin && $media->user_id !== $user->getAuthIdentifier()) {
            throw MediaException::unauthorized('rename');
        }

        // Sanitize name: remove path separators and handle extension
        $newName = basename($newName);
        
        // Preserve original extension if not provided in new name
        $originalExt = pathinfo($media->file_name, PATHINFO_EXTENSION);
        $newExt = pathinfo($newName, PATHINFO_EXTENSION);
        
        if (empty($newExt) && !empty($originalExt)) {
            $newName .= '.' . $originalExt;
        }

        $oldName = $media->file_name;
        $media->update(['file_name' => $newName]);

        $this->logAudit($user->getAuthIdentifier(), 'update', $media, 
            ['file_name' => $oldName], 
            ['file_name' => $newName], 
            "Renamed media from {$oldName} to {$newName}"
        );

        return $media;
    }

    /**
     * Update media visibility
     *
     * @param Authenticatable $user
     * @param Media $media
     * @param string $visibility 'public', 'private', 'shared'
     * @param bool $allowDownload
     * @return Media
     * @throws MediaException
     */
    public function updateVisibility(Authenticatable $user, Media $media, string $visibility, bool $allowDownload = true): Media
    {
        $isAdmin = \App\Support\AdminAccessResolver::canAccessAdmin($user);
        if (!$isAdmin && $media->user_id !== $user->getAuthIdentifier()) {
            throw MediaException::unauthorized('update visibility');
        }

        if (!in_array($visibility, ['public', 'private', 'shared'])) {
            throw MediaException::validationFailed("Invalid visibility level: {$visibility}");
        }

        $oldVisibility = $media->visibility;
        $updateData = [
            'visibility' => $visibility,
            'allow_download' => $allowDownload
        ];

        // Generate token if switching to shared and none exists
        if ($visibility === 'shared' && empty($media->share_token)) {
            $updateData['share_token'] = (string) Str::uuid();
        }

        $media->update($updateData);

        $this->logAudit($user->getAuthIdentifier(), 'update_visibility', $media,
            ['visibility' => $oldVisibility],
            $updateData,
            "Changed visibility to {$visibility}"
        );

        // Notify if newly shared
        if ($visibility === 'shared' && $oldVisibility !== 'shared') {
            // Cast to User instance for notification
            if ($user instanceof User) {
                $user->notify(new SecureShareCreated($media));
            }
        }

        return $media;
    }

    /**
     * Initialize a multipart upload
     *
     * @param Authenticatable $user
     * @param string $fileName
     * @param int $totalSize
     * @param string $mimeType
     * @return array
     */
    public function initMultipartUpload(Authenticatable $user, string $fileName, int $totalSize, string $mimeType): array
    {
        // Total quota check
        $totalUserSize = Media::where('user_id', $user->getAuthIdentifier())->sum('file_size');
        $quotaLimitMB = $this->subscriptionService->getFeatureValue($user, 'media-storage-mb');
        
        if ($quotaLimitMB && is_numeric($quotaLimitMB)) {
            $quotaLimitBytes = (int)$quotaLimitMB * 1024 * 1024;
            if (($totalUserSize + $totalSize) > $quotaLimitBytes) {
                throw MediaException::validationFailed("Storage quota exceeded. Limit: {$quotaLimitMB}MB");
            }
        }

        $uploadId = Str::uuid()->toString();
        $chunkDir = "chunks/{$uploadId}";
        
        Storage::disk('local')->makeDirectory($chunkDir);

        return [
            'upload_id' => $uploadId,
            'chunk_size' => 5 * 1024 * 1024, // 5MB default chunk size
            'file_name' => $fileName,
            'total_size' => $totalSize,
            'mime_type' => $mimeType,
        ];
    }

    /**
     * Upload a chunk for a multipart upload
     *
     * @param string $uploadId
     * @param int $chunkIndex
     * @param UploadedFile $chunk
     * @return bool
     */
    public function uploadChunk(string $uploadId, int $chunkIndex, UploadedFile $chunk): bool
    {
        $chunkDir = "chunks/{$uploadId}";
        
        if (!Storage::disk('local')->exists($chunkDir)) {
            throw MediaException::validationFailed("Invalid upload ID");
        }

        $path = Storage::disk('local')->putFileAs($chunkDir, $chunk, "chunk_{$chunkIndex}");
        
        return (bool) $path;
    }

    /**
     * Complete a multipart upload and assemble the file
     *
     * @param Authenticatable $user
     * @param string $uploadId
     * @param string $fileName
     * @param string $mimeType
     * @return Media
     */
    public function completeMultipartUpload(Authenticatable $user, string $uploadId, string $fileName, string $mimeType): Media
    {
        $chunkDir = "chunks/{$uploadId}";
        $localDisk = Storage::disk('local');
        
        if (!$localDisk->exists($chunkDir)) {
            throw MediaException::validationFailed("Invalid upload ID");
        }

        $chunks = $localDisk->files($chunkDir);
        sort($chunks, SORT_NATURAL);

        // Assemble file in a temporary location
        $tempPath = storage_path("app/temp_{$uploadId}");
        $fileHandle = fopen($tempPath, 'w');

        foreach ($chunks as $chunk) {
            $chunkContent = $localDisk->get($chunk);
            fwrite($fileHandle, $chunkContent);
        }

        fclose($fileHandle);

        // Now treat as a regular file upload to Media table
        $uploadedFile = new UploadedFile(
            $tempPath,
            $fileName,
            $mimeType,
            null,
            true // internal upload
        );

        try {
            $media = $this->uploadMedia($user, $uploadedFile);
            
            // Cleanup
            $localDisk->deleteDirectory($chunkDir);
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return $media;
        } catch (\Exception $e) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            throw $e;
        }
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
    public function deleteMedia(Authenticatable $user, Media $media, bool $force = false): bool
    {
        try {
            // Ownership check (with admin bypass)
            $isAdmin = \App\Support\AdminAccessResolver::canAccessAdmin($user);
            if (!$isAdmin && $media->user_id !== $user->getAuthIdentifier()) {
                throw MediaException::unauthorized('delete');
            }

            // Only allow deletion without force if the media is marked temporary
            // (temporary_until set). Prevent accidental deletion of permanent/shared media
            // from client-side quick actions. To delete permanent media, caller must
            // explicitly pass `force=true` (and should be an admin or deliberate flow).
            if (!$force && is_null($media->temporary_until)) {
                throw MediaException::validationFailed('Only temporary media can be deleted without confirmation. Use force=true to permanently delete.');
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
    public function validateFile(UploadedFile $file, ?Authenticatable $user = null): array
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

        // Determine max file size (from plan or fallback)
        $maxSize = self::FILE_LIMITS[$type] ?? 5 * 1024 * 1024;
        
        if ($user) {
            $planLimit = $this->subscriptionService->getFeatureValue($user, 'media-max-upload-mb');
            if ($planLimit && is_numeric($planLimit)) {
                $maxSize = (int)$planLimit * 1024 * 1024;
            }
        }

        if ($file->getSize() > $maxSize) {
            $maxSizeMB = round($maxSize / (1024 * 1024), 2);
            return [
                'valid' => false,
                'type' => $type,
                'error' => "File exceeds maximum size of {$maxSizeMB}MB",
            ];
        }

        // Total quota check
        if ($user) {
            $totalSize = Media::where('user_id', $user->getAuthIdentifier())->sum('file_size');
            $quotaLimitMB = $this->subscriptionService->getFeatureValue($user, 'media-storage-mb');
            
            if ($quotaLimitMB && is_numeric($quotaLimitMB)) {
                $quotaLimitBytes = (int)$quotaLimitMB * 1024 * 1024;
                if (($totalSize + $file->getSize()) > $quotaLimitBytes) {
                    return [
                        'valid' => false,
                        'type' => $type,
                        'error' => "Storage quota exceeded. Limit: {$quotaLimitMB}MB",
                    ];
                }
            }
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
     * Check storage thresholds and notify user if 80% or 100% is reached
     *
     * @param Authenticatable $user
     * @param int $addedSize
     * @return void
     */
    private function checkAndNotifyStorageThresholds(Authenticatable $user, int $addedSize): void
    {
        // Type cast to User for notification
        if (!($user instanceof User)) {
            return;
        }
        /** @var User $userInstance */
        $quotaLimitMB = $this->subscriptionService->getFeatureValue($user, 'media-storage-mb');
        if (!$quotaLimitMB || !is_numeric($quotaLimitMB)) {
            return;
        }

        $quotaLimitBytes = (int)$quotaLimitMB * 1024 * 1024;
        $totalSizeBefore = Media::where('user_id', $user->getAuthIdentifier())->sum('file_size') - $addedSize;
        $totalSizeAfter = $totalSizeBefore + $addedSize;

        $percentageBefore = ($totalSizeBefore / $quotaLimitBytes) * 100;
        $percentageAfter = ($totalSizeAfter / $quotaLimitBytes) * 100;

        // Notify at 100%
        if ($percentageBefore < 100 && $percentageAfter >= 100) {
            $userInstance->notify(new StorageQuotaExceeded((int)$quotaLimitMB));
        } 
        // Notify at 80%
        elseif ($percentageBefore < 80 && $percentageAfter >= 80) {
            $userInstance->notify(new StorageUsageAlert(80, (int)$quotaLimitMB));
        }
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
