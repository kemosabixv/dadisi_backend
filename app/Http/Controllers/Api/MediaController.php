<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    // File size limits (in bytes)
    private const FILE_LIMITS = [
        'image' => 5 * 1024 * 1024,      // 5 MB
        'audio' => 10 * 1024 * 1024,     // 10 MB
        'video' => 50 * 1024 * 1024,     // 50 MB
        'pdf' => 30 * 1024 * 1024,       // 30 MB
        'gif' => 5 * 1024 * 1024,        // 5 MB
    ];

    private const ALLOWED_MIMES = [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
        'audio' => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm'],
        'video' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
        'pdf' => ['application/pdf'],
        'gif' => ['image/gif'],
    ];

    /**
     * List My Media
     *
     * Retrieves a paginated list of media files uploaded by the authenticated user.
     * Supports filtering by media type (e.g., images only) and searching by filename.
     *
     * @group Media Management
     * @groupDescription Endpoints for uploading, retrieving, and managing user-generated content (files, images, documents). Enforces storage quotas and file type restrictions.
     * @authenticated
     * @description Retrieve authenticated user's media files (images, audio, video, PDFs, GIFs)
     *
     * @queryParam type string optional Filter by media type (image, audio, video, pdf, gif). Example: image
     * @queryParam search string optional Search for media by file name. Example: profile_pic
     * @queryParam per_page integer optional Number of items per page. Default: 30. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "file_name": "jane_doe_avatar.jpg",
     *       "file_path": "/media/2025-12/jane_doe_avatar.jpg",
     *       "type": "image",
     *       "mime_type": "image/jpeg",
     *       "file_size": 245000,
     *       "is_public": false,
     *       "attached_to": "profile_picture",
     *       "created_at": "2025-12-04T10:00:00Z",
     *       "url": "https://api.dadisilab.com/storage/media/2025-12/jane_doe_avatar.jpg"
     *     }
     *   ],
     *   "pagination": {"total": 1, "per_page": 30, "current_page": 1}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $query = Media::ownedBy(Auth::id());

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Search by filename
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('file_name', 'like', "%{$search}%");
        }

        $media = $query->latest()->paginate($request->per_page ?? 30);

        // Add access URLs
        $data = $media->map(fn($item) => array_merge($item->toArray(), [
            'url' => $this->getMediaUrl($item),
        ]));

        return response()->json([
            'success' => true,
            'data' => $data->all(),
            'pagination' => [
                'total' => $media->total(),
                'per_page' => $media->perPage(),
                'current_page' => $media->currentPage(),
            ],
        ]);
    }

    /**
     * Upload Media File
     *
     * Uploads a new media file to the server.
     * The system automatically validates file types and enforcing size limits:
     * - Images: 5MB
     * - Audio: 10MB
     * - Video: 50MB
     * - PDF: 30MB
     * - GIF: 5MB
     *
     * @group Media Management
     * @authenticated
     *
     * @bodyParam file file required The binary file to upload. Must be a supported MIME type.
     * @bodyParam attached_to string optional Context tag for the file (e.g., 'profile_header', 'post_image'). Example: post
     * @bodyParam attached_to_id integer optional ID of the related resource. Example: 101
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Media uploaded successfully",
     *   "data": {
     *     "id": 2,
     *     "file_name": "research_notes.pdf",
     *     "file_path": "/media/2025-12/research_notes-abc123.pdf",
     *     "type": "pdf",
     *     "mime_type": "application/pdf",
     *     "file_size": 1540000,
     *     "is_public": false,
     *     "url": "https://api.dadisilab.com/storage/media/2025-12/research_notes-abc123.pdf"
     *   }
     * }
     * @response 422 {
     *   "success": false,
     *   "message": "File upload failed",
     *   "errors": {"file": ["File exceeds maximum size of 5MB"]}
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file',
            'attached_to' => 'nullable|string|max:50',
            'attached_to_id' => 'nullable|integer',
            // Optional flag to mark upload as temporary (for dev editor fallback)
            'temporary' => 'sometimes|boolean',
        ]);

        $file = $request->file('file');
        $mimeType = $file->getMimeType();

        // Determine file type
        $type = $this->getFileType($mimeType);
        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported file type',
                'errors' => ['file' => ['File type is not supported']],
            ], 422);
        }

        // Check file size
        $maxSize = self::FILE_LIMITS[$type] ?? 5 * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            $maxSizeMB = $maxSize / (1024 * 1024);
            return response()->json([
                'success' => false,
                'message' => 'File upload failed',
                'errors' => ['file' => ["File exceeds maximum size of {$maxSizeMB}MB"]],
            ], 422);
        }

        try {
            // Store file with unique name
            $directory = 'media/' . now()->format('Y-m');
            $originalName = $file->getClientOriginalName();
            $uniqueName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '-' . Str::random(8) . '.' . $file->getClientOriginalExtension();

            $path = Storage::disk('public')->putFileAs($directory, $file, $uniqueName);

            // Determine temporary expiration from retention settings if flag set
            $temporaryUntil = null;
            if ($request->boolean('temporary')) {
                $minutes = \App\Models\UserDataRetentionSetting::getRetentionMinutes('temporary_media');
                $temporaryUntil = now()->addMinutes($minutes);
            }

            // Create media record
            $media = Media::create([
                'user_id' => Auth::id(),
                'file_name' => $originalName,
                'file_path' => '/' . $path,
                'type' => $type,
                'mime_type' => $mimeType,
                'file_size' => $file->getSize(),
                'is_public' => false,
                'attached_to' => $validated['attached_to'] ?? null,
                'attached_to_id' => $validated['attached_to_id'] ?? null,
                'temporary_until' => $temporaryUntil,
            ]);

            $this->logAuditAction('create', Media::class, $media->id, null, $media->only(['file_name', 'type', 'file_size']), "Uploaded media: {$media->file_name}");

            return response()->json([
                'success' => true,
                'message' => 'Media uploaded successfully',
                'data' => array_merge($media->toArray(), [
                    'url' => $this->getMediaUrl($media),
                ]),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Media upload failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'File upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete Media File
     *
     * Permanently deletes a specific media file from storage and the database.
     * Users can only delete files they uploaded themselves.
     *
     * @group Media Management
     * @authenticated
     *
     * @urlParam media integer required The ID of the media record to delete. Example: 42
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Media deleted successfully"
     * }
     * @response 403 {
     *   "message": "This action is unauthorized."
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "Media not found"
     * }
     */
    public function destroy(Media $media): JsonResponse
    {
        // Ownership check
        if ($media->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this media',
            ], 403);
        }

        try {
            // Delete file from storage
            if ($media->file_path) {
                Storage::disk('public')->delete(ltrim($media->file_path, '/'));
            }

            $this->logAuditAction('delete', Media::class, $media->id, $media->only(['file_name', 'type']), null, "Deleted media: {$media->file_name}");
            $media->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Media deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Media deletion failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Media deletion failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Media Details
     *
     * Retrieves the metadata and public URL for a specific media file.
     * Securely checks ownership before returning details.
     *
     * @group Media Management
     * @authenticated
     *
     * @urlParam media integer required The unique ID of the media file. Example: 1

     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "file_name": "jane_doe_avatar.jpg",
     *     "type": "image",
     *     "mime_type": "image/jpeg",
     *     "file_size": 245000,
     *     "is_public": false,
     *     "created_at": "2025-12-04T10:00:00Z",
     *     "url": "https://api.dadisilab.com/storage/media/2025-12/jane_doe_avatar.jpg"
     *   }
     * }
     */
    public function show(Media $media): JsonResponse
    {
        // Ownership check
        if ($media->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this media',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge($media->toArray(), [
                'url' => $this->getMediaUrl($media),
            ]),
        ]);
    }

    /**
     * Determine file type from MIME type
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
     * Get full URL for media file
     */
    private function getMediaUrl(Media $media): string
    {
        return url('/storage' . $media->file_path);
    }

    private function logAuditAction(string $action, string $modelType, int $modelId, ?array $oldValues, ?array $newValues, ?string $notes = null): void
    {
        try {
            AuditLog::create([
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'user_id' => auth()->id(),
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'notes' => $notes,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create audit log', ['error' => $e->getMessage()]);
        }
    }
}
