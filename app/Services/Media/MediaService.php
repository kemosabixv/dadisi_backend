<?php

namespace App\Services\Media;

use App\Exceptions\MediaException;
use App\Models\AuditLog;
use App\Models\Folder;
use App\Models\Media;
use App\Models\MediaFile;
use App\Models\User;
use App\Models\UserDataRetentionSetting;
use App\Notifications\SecureShareCreated;
use App\Notifications\StorageQuotaExceeded;
use App\Notifications\StorageUsageAlert;
use App\Services\Contracts\MediaServiceContract;
use App\Services\SubscriptionCoreService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService implements MediaServiceContract
{
    private const FILE_LIMITS = [
        'image' => 5 * 1024 * 1024,
        'audio' => 10 * 1024 * 1024,
        'video' => 50 * 1024 * 1024,
        'pdf' => 30 * 1024 * 1024,
        'gif' => 5 * 1024 * 1024,
    ];

    private const ALLOWED_MIMES = [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
        'audio' => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm'],
        'video' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
        'pdf' => ['application/pdf'],
        'gif' => ['image/gif'],
        'document' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
        ],
    ];

    public function __construct(private SubscriptionCoreService $subscriptionService) {}

    /**
     * Upload media using CAS (Content-Addressable Storage)
     */
    public function uploadMedia(Authenticatable $user, UploadedFile $file, array $metadata = []): Media
    {
        $rootType = $metadata['root_type'] ?? 'personal';
        $skipQuota = $metadata['skip_quota'] ?? ($rootType === 'public');

        $validation = $this->validateFile($file, $user, $skipQuota);
        if (! $validation['valid']) {
            throw MediaException::validationFailed($validation['error']);
        }

        $type = $validation['type'];
        $mimeType = $file->getMimeType();
        $hash = hash_file('sha256', $file->getRealPath());

        return DB::transaction(function () use ($user, $file, $metadata, $type, $mimeType, $hash, $rootType) {
            // 1. Check if the physical file exists (CAS)
            $mediaFile = MediaFile::where('hash', $hash)->first();

            if (! $mediaFile) {
                // Upload to R2
                $path = 'blobs/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash;
                if (! Storage::disk('r2')->put($path, file_get_contents($file->getRealPath()))) {
                    throw MediaException::storageError('Failed to store file in R2');
                }

                $mediaFile = MediaFile::create([
                    'hash' => $hash,
                    'disk' => 'r2',
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $mimeType,
                    'ref_count' => 0,
                ]);
            }

            // 2. Increment physical reference count
            $mediaFile->increment('ref_count');

            // 3. Handle folder logic
            $folderId = $metadata['folder_id'] ?? null;
            if (! $folderId) {
                if (! empty($metadata['path'])) {
                    $folderId = $this->ensureSubfolder($user, $rootType, (array) $metadata['path'])->id;
                }
                // If no path and no folder_id, stays at root (null)
            }

            // 4. Create virtual media record
            $temporaryUntil = null;
            if (! empty($metadata['temporary'])) {
                $minutes = UserDataRetentionSetting::getRetentionMinutes('temporary_media');
                $temporaryUntil = now()->addMinutes($minutes);
            }

            $media = Media::create([
                'user_id' => $user->getAuthIdentifier(),
                'media_file_id' => $mediaFile->id,
                'folder_id' => $folderId,
                'file_name' => $file->getClientOriginalName() ?? 'file_'.time(),
                'type' => $type,
                'mime_type' => $mimeType,
                'file_size' => $file->getSize(),
                'root_type' => $rootType,
                'visibility' => $metadata['visibility'] ?? ($rootType === 'public' ? 'public' : 'private'),
                'temporary_until' => $temporaryUntil,
            ]);

            $this->logAudit($user->getAuthIdentifier(), 'create', $media, null, $media->toArray());
            $this->checkAndNotifyStorageThresholds($user, $media->file_size);

            return $media;
        });
    }

    /**
     * List media within a folder
     */
    public function listMedia(Authenticatable $user, array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        $query = Media::with('file');

        // Access Control: Everyone (Users and Admins) can ONLY see their own media.
        // Browsing is strictly owner-centric to prevent gallery snooping.
        $query->where('user_id', $user->getAuthIdentifier());

        if (! empty($filters['root_type'])) {
            $query->where('root_type', $filters['root_type']);
        }

        if (array_key_exists('folder_id', $filters)) {
            $query->where('folder_id', $filters['folder_id']);

            // Further security: If listing a specific folder, ensure the user owns that folder.
            if ($filters['folder_id'] !== null) {
                $folder = Folder::find($filters['folder_id']);
                if ($folder && $folder->user_id !== $user->getAuthIdentifier()) {
                    throw MediaException::unauthorized('list');
                }
            }
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $query->where('file_name', 'like', "%{$filters['search']}%");
        }

        if (isset($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Delete virtual media and cleanup physical CAS if ref_count hits 0
     */
    public function deleteMedia(Authenticatable $user, Media $media): bool
    {
        if ($media->user_id !== $user->getAuthIdentifier() && ! \App\Support\AdminAccessResolver::canAccessAdmin($user)) {
            throw MediaException::unauthorized('delete');
        }

        if ($media->usage_count > 0) {
            throw MediaException::validationFailed('Media is currently in use by contents. Deletion blocked.');
        }

        return DB::transaction(function () use ($user, $media) {
            $mediaFile = $media->file;

            // 1. Log Audit
            $this->logAudit($user->getAuthIdentifier(), 'delete', $media, $media->toArray(), null);

            // 2. Delete Virtual Media
            $media->forceDelete();

            // 3. Decrement Physical Ref Count & Purge if zero
            if ($mediaFile) {
                $mediaFile->decrement('ref_count');
                if ($mediaFile->ref_count <= 0) {
                    Storage::disk($mediaFile->disk)->delete($mediaFile->path);
                    $mediaFile->delete();
                }
            }

            return true;
        });
    }

    /**
     * Move media to a new folder
     */
    public function moveMedia(Authenticatable $user, Media $media, ?int $folderId, ?string $rootType = null): Media
    {
        if ($media->user_id !== $user->getAuthIdentifier() && ! \App\Support\AdminAccessResolver::canAccessAdmin($user)) {
            throw MediaException::unauthorized('move');
        }

        $targetFolder = $folderId ? Folder::findOrFail($folderId) : null;
        $targetRoot = $targetFolder ? $targetFolder->root_type : ($rootType ?? $media->root_type);

        // Policy: Deny public -> personal
        if ($media->root_type === 'public' && $targetRoot === 'personal') {
            throw MediaException::badRequest('Moving media from public to personal root is not allowed.');
        }

        $updateData = ['folder_id' => $folderId];

        // Policy: Personal -> Public (Manual Move)
        if ($media->root_type === 'personal' && $targetRoot === 'public') {
            $updateData['root_type'] = 'public';
            $updateData['visibility'] = 'public';

            // Set temporary_until for manual moves to public (Orphan Policy)
            $minutes = UserDataRetentionSetting::getRetentionMinutes('temporary_media');
            $updateData['temporary_until'] = now()->addMinutes($minutes);
        }

        $media->update($updateData);

        return $media;
    }

    /**
     * Get the virtual root (dummy method for interface compatibility, returns null)
     */
    public function getOrCreateRootFolder(Authenticatable $user, string $type): ?Folder
    {
        return null;
    }

    /**
     * Promote media to public and move to tiered hierarchy
     * /Public/{type}/{slug}/
     */
    public function promoteToPublic(Media $media, string $type, ?string $slug = null): Media
    {
        // Align 'topics' to 'threads' per policy
        if ($type === 'topics') {
            $type = 'forum-threads';
        }

        $user = User::find($media->user_id);
        if (! $user) {
            return $media;
        }

        $path = [$type];
        if ($slug) {
            $path[] = $slug;
        }

        $targetFolder = $this->ensureSubfolder($user, 'public', $path);

        $media->update([
            'folder_id' => $targetFolder->id,
            'visibility' => 'public',
            'temporary_until' => null, // Promotion makes it permanent
        ]);

        return $media;
    }

    /**
     * Ensure a subfolder exists within a root type (personal or public)
     */
    protected function ensureSubfolder(Authenticatable $user, string $rootType, array $path): Folder
    {
        $currentParentId = null;
        $lastFolder = null;

        foreach ($path as $segment) {
            $lastFolder = Folder::firstOrCreate([
                'user_id' => $user->getAuthIdentifier(),
                'parent_id' => $currentParentId,
                'name' => $segment,
                'root_type' => $rootType,
            ], [
                'is_system' => $rootType === 'public',
            ]);
            $currentParentId = $lastFolder->id;
        }

        if (! $lastFolder) {
            throw new \Exception('Cannot ensure subfolder with empty path');
        }

        return $lastFolder;
    }

    /**
     * Validation with Plan-Specific Quotas and Policy Exemptions
     */
    public function validateFile(UploadedFile $file, ?Authenticatable $user = null, bool $skipQuota = false): array
    {
        $mimeType = $file->getMimeType();
        $type = $this->getFileType($mimeType);

        if (! $type) {
            return ['valid' => false, 'type' => null, 'error' => "Unsupported type: {$mimeType}"];
        }

        // 1. Check file-type specific limits
        $fileTypeLimit = self::FILE_LIMITS[$type] ?? (10 * 1024 * 1024); // Default 10MB

        // 1a. Apply plan-level max upload to override per-type cap if configured
        if ($user) {
            $planMaxUploadMB = $this->subscriptionService->getFeatureValue($user, 'media-max-upload-mb');
            if ($planMaxUploadMB !== null && is_numeric($planMaxUploadMB) && $planMaxUploadMB > 0) {
                $fileTypeLimit = (int) $planMaxUploadMB * 1024 * 1024;
            }
        }

        if ($file->getSize() > $fileTypeLimit) {
            $maxMB = round($fileTypeLimit / (1024 * 1024), 2);

            return ['valid' => false, 'type' => $type, 'error' => "File exceeds maximum size of {$maxMB}MB for {$type}"];
        }

        // 3. Check user storage quota
        if ($user && ! $skipQuota) {
            // POLICY: Exempt media in Public folders or with usage_count > 0 from storage limits.
            // This avoids penalizing users for NGO contributions.
            $totalUsed = $this->getStorageUsage($user);

            // Get aggregated storage limit from subscription service
            $planLimitMB = $this->subscriptionService->getFeatureValue($user, 'media-storage-mb') ?? 20;
            $limitBytes = $planLimitMB * 1024 * 1024;

            if (($totalUsed + $file->getSize()) > $limitBytes) {
                return ['valid' => false, 'type' => $type, 'error' => "Quota exceeded ({$planLimitMB}MB max)"];
            }
        }

        return ['valid' => true, 'type' => $type, 'error' => null];
    }

    public function getStorageUsage(Authenticatable $user): int
    {
        return (int) Media::where('user_id', $user->getAuthIdentifier())
            ->where(function ($q) {
                $q->where('usage_count', 0)
                    ->orWhereNull('usage_count');
            })
            ->where('root_type', 'personal')
            ->sum('file_size');
    }

    public function getStorageQuota(Authenticatable $user): array
    {
        $limitMB = $this->subscriptionService->getFeatureValue($user, 'media-storage-mb') ?? 20;
        $limitBytes = (int) $limitMB * 1024 * 1024;
        $usedBytes = $this->getStorageUsage($user);

        return [
            'used_bytes' => $usedBytes,
            'limit_bytes' => $limitBytes,
            'limit_mb' => $limitMB,
            'percentage' => $limitBytes > 0 ? min(round(($usedBytes / $limitBytes) * 100, 2), 100) : 100,
        ];
    }

    private function getFileType(string $mimeType): ?string
    {
        foreach (self::ALLOWED_MIMES as $type => $mimes) {
            if (in_array($mimeType, $mimes)) {
                return $type;
            }
        }

        return null;
    }

    private function logAudit($userId, $action, $media, $old, $new): void
    {
        AuditLog::create([
            'action' => $action,
            'model_type' => Media::class,
            'model_id' => $media->id,
            'user_id' => $userId,
            'old_values' => $old ? json_encode($old) : null,
            'new_values' => $new ? json_encode($new) : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function checkAndNotifyStorageThresholds(Authenticatable $user, int $addedSize): void
    {
        if (! ($user instanceof User)) {
            return;
        }

        $limitMB = $this->subscriptionService->getFeatureValue($user, 'media-storage-mb') ?? 20;
        $limitBytes = (int) $limitMB * 1024 * 1024;

        $total = Media::where('user_id', $user->id)->sum('file_size');
        $percent = ($total / $limitBytes) * 100;

        if ($percent >= 100) {
            $user->notify(new StorageQuotaExceeded($limitMB));
        } elseif ($percent >= 80) {
            $user->notify(new StorageUsageAlert(80, $limitMB));
        }
    }

    // Required by Interface
    public function getMedia(string|int $id): Media
    {
        $media = Media::find($id);
        if (! $media) {
            throw MediaException::notFound($id);
        }

        return $media;
    }

    public function getMediaUrl(Media $media): string
    {
        return $media->url;
    }

    public function streamMedia(Media $media): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $disk = $media->file?->disk ?? 'r2';
        $path = $media->file?->path;

        if (! $path || ! Storage::disk($disk)->exists($path)) {
            throw MediaException::notFound($media->id);
        }

        return Storage::disk($disk)->response($path, $media->file_name, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => ($media->allow_download ? 'attachment' : 'inline').'; filename="'.$media->file_name.'"',
        ]);
    }

    public function getAllowedMimeTypes(): array
    {
        return self::ALLOWED_MIMES;
    }

    public function getFileSizeLimits(): array
    {
        return self::FILE_LIMITS;
    }

    public function initMultipartUpload(Authenticatable $user, string $fileName, int $totalSize, string $mimeType, array $metadata = []): array
    {
        $uploadId = Str::uuid()->toString();

        // 1. Optional early quota check (approximate)
        $rootType = $metadata['root_type'] ?? 'personal';
        $skipQuota = $metadata['skip_quota'] ?? ($rootType === 'public');

        if (! $skipQuota) {
            $totalUsed = Media::where('user_id', $user->getAuthIdentifier())
                ->where('usage_count', 0)
                ->whereHas('folder', function ($q) {
                    $q->where('root_type', '!=', 'public');
                })
                ->sum('file_size');

            $planLimitMB = $this->subscriptionService->getFeatureValue($user, 'media-storage-mb') ?? 20;
            $limitBytes = (int) $planLimitMB * 1024 * 1024;

            if (($totalUsed + $totalSize) > $limitBytes) {
                throw MediaException::validationFailed("Quota exceeded ({$planLimitMB}MB max)");
            }
        }

        // Ensure temporary storage exists
        Storage::disk('local')->makeDirectory("chunks/{$uploadId}");

        // Persist metadata for completeMultipartUpload
        Storage::disk('local')->put("chunks/{$uploadId}/metadata.json", json_encode($metadata));

        return [
            'upload_id' => $uploadId,
            'file_name' => $fileName,
            'total_size' => $totalSize,
        ];
    }

    public function uploadChunk(string $uploadId, int $chunkIndex, UploadedFile $chunk): bool
    {
        $path = "chunks/{$uploadId}/part_{$chunkIndex}";

        return Storage::disk('local')->put($path, file_get_contents($chunk->getRealPath()));
    }

    public function completeMultipartUpload(Authenticatable $user, string $uploadId, string $fileName, string $mimeType, array $metadata = []): Media
    {
        $chunkPath = "chunks/{$uploadId}";
        if (! Storage::disk('local')->exists($chunkPath)) {
            throw MediaException::notFound("Upload session not found: {$uploadId}");
        }

        // 0. Load persisted metadata
        $persistedMetadata = [];
        if (Storage::disk('local')->exists("{$chunkPath}/metadata.json")) {
            $persistedMetadata = json_decode(Storage::disk('local')->get("{$chunkPath}/metadata.json"), true) ?? [];
        }
        $metadata = array_merge($persistedMetadata, $metadata);

        // 1. Assemble file
        $tempPath = storage_path('app/private/temp_'.$uploadId);
        $tempFile = fopen($tempPath, 'wb');

        $allFiles = Storage::disk('local')->files($chunkPath);
        $chunks = array_filter($allFiles, fn ($f) => basename($f) !== 'metadata.json');
        sort($chunks, SORT_NATURAL);

        foreach ($chunks as $chunk) {
            $content = Storage::disk('local')->get($chunk);
            fwrite($tempFile, $content);
        }
        fclose($tempFile);

        // 2. Process as normal upload but from temp file
        try {
            $hash = hash_file('sha256', $tempPath);
            $size = filesize($tempPath);
            $type = $this->getFileType($mimeType);

            if (! $type) {
                throw MediaException::unsupportedFileType($mimeType);
            }

            // Quota check
            $rootType = $metadata['root_type'] ?? 'personal';
            $skipQuota = $metadata['skip_quota'] ?? ($rootType === 'public');

            if (! $skipQuota) {
                $totalUsed = Media::where('user_id', $user->getAuthIdentifier())
                    ->where('usage_count', 0)
                    ->whereHas('folder', function ($q) {
                        $q->where('root_type', '!=', 'public');
                    })
                    ->sum('file_size');

                $planLimitMB = $this->subscriptionService->getFeatureValue($user, 'media-storage-mb') ?? 20;
                $limitBytes = (int) $planLimitMB * 1024 * 1024;

                if (($totalUsed + $size) > $limitBytes) {
                    throw MediaException::validationFailed("Quota exceeded ({$planLimitMB}MB max)");
                }
            }

            return DB::transaction(function () use ($user, $tempPath, $hash, $size, $mimeType, $type, $fileName, $uploadId, $metadata, $rootType) {
                $mediaFile = MediaFile::where('hash', $hash)->first();

                if (! $mediaFile) {
                    $path = 'blobs/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash;
                    if (! Storage::disk('r2')->put($path, file_get_contents($tempPath))) {
                        throw MediaException::storageError('Failed to store assembled file in R2');
                    }

                    $mediaFile = MediaFile::create([
                        'hash' => $hash,
                        'disk' => 'r2',
                        'path' => $path,
                        'size' => $size,
                        'mime_type' => $mimeType,
                        'ref_count' => 0,
                    ]);
                }

                $mediaFile->increment('ref_count');

                // Folder logic
                $folderId = $metadata['folder_id'] ?? null;
                if (! $folderId) {
                    if (! empty($metadata['path'])) {
                        $folderId = $this->ensureSubfolder($user, $rootType, (array) $metadata['path'])->id;
                    } elseif (! empty($rootType)) {
                        $folderId = $this->getOrCreateRootFolder($user, $rootType)->id;
                    }
                }

                $rootType = $metadata['root_type'] ?? 'public';
                $temporaryUntil = null;
                if (! empty($metadata['temporary'])) {
                    $minutes = UserDataRetentionSetting::getRetentionMinutes('temporary_media');
                    $temporaryUntil = now()->addMinutes($minutes);
                }

                $media = Media::create([
                    'user_id' => $user->getAuthIdentifier(),
                    'media_file_id' => $mediaFile->id,
                    'folder_id' => $folderId,
                    'file_name' => $fileName,
                    'type' => $type,
                    'mime_type' => $mimeType,
                    'file_size' => $size,
                    'root_type' => $rootType,
                    'visibility' => $metadata['visibility'] ?? (($rootType === 'public') ? 'public' : 'private'),
                    'temporary_until' => $temporaryUntil,
                ]);

                // Cleanup
                Storage::disk('local')->deleteDirectory("chunks/{$uploadId}");
                @unlink($tempPath);

                $this->checkAndNotifyStorageThresholds($user, $size);

                return $media;
            });
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function renameMedia(Authenticatable $user, Media $media, string $newName): Media
    {
        $media->update(['file_name' => $newName]);

        return $media;
    }

    public function updateVisibility(Authenticatable $user, Media $media, string $visibility, bool $allowDownload = true, ?string $expiresAt = null): Media
    {
        // Policy: Public root media or already public media is immutable visibility
        if ($media->root_type === 'public' || $media->visibility === 'public') {
            throw MediaException::badRequest('Public media visibility cannot be changed.');
        }

        // Policy: Deny 'public' in Personal root
        if ($media->root_type === 'personal' && $visibility === 'public') {
            throw MediaException::badRequest('Media in personal root cannot be marked as public. Move it to a public folder to promote it.');
        }

        $updateData = [
            'visibility' => $visibility,
            'allow_download' => $allowDownload,
            'expires_at' => $expiresAt ? \Carbon\Carbon::parse($expiresAt) : null,
        ];

        if ($visibility === 'shared' && ! $media->share_token) {
            $updateData['share_token'] = (string) Str::uuid();
        }

        $media->update($updateData);

        if ($visibility === 'shared' && $user instanceof User) {
            $user->notify(new SecureShareCreated($media));
        }

        return $media;
    }

    /**
     * Register an existing file on disk into the CAS system
     */
    public function registerFile(Authenticatable $user, string $absolutePath, string $originalName, array $metadata = []): Media
    {
        if (! file_exists($absolutePath)) {
            throw MediaException::notFound("Local file not found at: {$absolutePath}");
        }

        $mimeType = mime_content_type($absolutePath);
        $type = $this->getFileType($mimeType);
        $size = filesize($absolutePath);
        $hash = hash_file('sha256', $absolutePath);

        return DB::transaction(function () use ($user, $absolutePath, $originalName, $metadata, $type, $mimeType, $hash, $size) {
            // 1. Check if the physical file exists (CAS)
            $mediaFile = MediaFile::where('hash', $hash)->first();

            if (! $mediaFile) {
                // Upload/Copy to R2
                $path = 'blobs/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash;
                if (! Storage::disk('r2')->put($path, file_get_contents($absolutePath))) {
                    throw MediaException::storageError('Failed to store file in R2 during registration');
                }

                $mediaFile = MediaFile::create([
                    'hash' => $hash,
                    'disk' => 'r2',
                    'path' => $path,
                    'size' => $size,
                    'mime_type' => $mimeType,
                    'ref_count' => 0,
                ]);
            }

            // 2. Increment physical reference count
            $mediaFile->increment('ref_count');

            // 3. Handle folder logic
            $folderId = $metadata['folder_id'] ?? null;
            if (! $folderId) {
                if (! empty($metadata['path']) && is_array($metadata['path'])) {
                    $folderId = $this->ensureSubfolder($user, $metadata['root_type'] ?? 'public', $metadata['path'])->id;
                }
                // Default stays as null (true root)
            }

            $rootType = $metadata['root_type'] ?? 'public';
            $temporaryUntil = null;
            if (! empty($metadata['temporary'])) {
                $minutes = UserDataRetentionSetting::getRetentionMinutes('temporary_media');
                $temporaryUntil = now()->addMinutes($minutes);
            }

            // 4. Create virtual media record
            $media = Media::create([
                'user_id' => $user->getAuthIdentifier(),
                'media_file_id' => $mediaFile->id,
                'folder_id' => $folderId,
                'file_name' => $originalName,
                'type' => $type,
                'mime_type' => $mimeType,
                'file_size' => $size,
                'root_type' => $rootType,
                'visibility' => $metadata['visibility'] ?? 'public',
                'temporary_until' => $temporaryUntil,
            ]);

            return $media;
        });
    }

    /**
     * Rename a folder in the tiered hierarchy
     */
    public function renameFolder(Authenticatable $user, string $rootType, array $oldPath, string $newSegment): bool
    {
        $currentParent = $this->getOrCreateRootFolder($user, $rootType);

        // Find the folder to rename
        foreach ($oldPath as $segment) {
            $query = Folder::where('user_id', $user->getAuthIdentifier())
                ->where('name', $segment)
                ->where('root_type', $rootType);

            if ($currentParent) {
                $query->where('parent_id', $currentParent->id);
            } else {
                $query->whereNull('parent_id');
            }

            $folder = $query->first();

            if (! $folder) {
                return false; // Path doesn't exist
            }
            $currentParent = $folder;
        }

        // $currentParent is now the folder at the end of the oldPath
        $currentParent->update(['name' => $newSegment]);

        return true;
    }
}
