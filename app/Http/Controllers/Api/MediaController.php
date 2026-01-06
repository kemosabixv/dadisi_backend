<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MediaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\Contracts\MediaServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @group Media Management
 * @groupDescription Endpoints for uploading, retrieving, and managing user-generated content (files, images, documents). Enforces storage quotas and file type restrictions.
 */
class MediaController extends Controller
{
    public function __construct(
        private MediaServiceContract $mediaService
    ) {
        $this->middleware('auth:sanctum')->except(['showShared']);
    }

    /**
     * List My Media
     *
     * Retrieves a paginated list of media files uploaded by the authenticated user.
     * Supports filtering by media type (e.g., images only) and searching by filename.
     *
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
        try {
            $user = $request->user();
            $perPage = min((int) $request->input('per_page', 30), 100);

            $filters = [
                'type' => $request->input('type'),
                'search' => $request->input('search'),
            ];

            $media = $this->mediaService->listMedia($user, $filters, $perPage);
            
            return MediaResource::collection($media)->additional([
                'success' => true,
                'pagination' => [
                    'total' => $media->total(),
                    'per_page' => $media->perPage(),
                    'current_page' => $media->currentPage(),
                    'last_page' => $media->lastPage(),
                ],
            ])->response();
        } catch (\Exception $e) {
            Log::error('Failed to retrieve media list', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve media list',
            ], 500);
        }
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
    public function store(StoreMediaRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $metadata = [
                'attached_to' => $validated['attached_to'] ?? null,
                'attached_to_id' => $validated['attached_to_id'] ?? null,
                'temporary' => $validated['temporary'] ?? false,
            ];

            $media = $this->mediaService->uploadMedia($user, $request->file('file'), $metadata);
 
             return (new MediaResource($media))->additional([
                 'success' => true,
                 'message' => 'Media uploaded successfully',
             ])->response()->setStatusCode(201);
        } catch (MediaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['file' => [$e->getMessage()]],
            ], $e->getCode() ?: 422);
        } catch (\Exception $e) {
            Log::error('Media upload failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Media Details
     *
     * Retrieves the metadata and public URL for a specific media file.
     * Securely checks ownership before returning details.
     *
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
     * @response 403 {"success": false, "message": "Unauthorized to view this media"}
     * @response 404 {"success": false, "message": "Media not found"}
     */
    public function show(Request $request, Media $media): JsonResponse
    {
        try {
            $user = $request->user();

            // Ownership check (with admin bypass)
            $isAdmin = \App\Support\AdminAccessResolver::canAccessAdmin($user);
            if (!$isAdmin && $media->user_id !== $user->id) {
                throw MediaException::unauthorized('view');
            }

            return (new MediaResource($media))->additional([
                'success' => true,
            ])->response();
        } catch (MediaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 403);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve media details', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve media details',
            ], 500);
        }
    }

    /**
     * Delete Media File
     *
     * Permanently deletes a specific media file from storage and the database.
     * Users can only delete files they uploaded themselves.
     *
     * @authenticated
     *
     * @urlParam media integer required The ID of the media record to delete. Example: 42
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Media deleted successfully"
     * }
     * @response 403 {"success": false, "message": "Unauthorized to delete this media"}
     * @response 404 {"success": false, "message": "Media not found"}
     */
    public function destroy(Request $request, Media $media): JsonResponse
    {
        try {
            $user = $request->user();

            $force = $request->boolean('force', false);
            $this->mediaService->deleteMedia($user, $media, $force);

            return response()->json([
                'success' => true,
                'message' => 'Media deleted successfully',
            ]);
        } catch (MediaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 403);
        } catch (\Exception $e) {
            Log::error('Media deletion failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'media_id' => $media->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Media deletion failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rename Media
     *
     * Changes the display name of a media file. This does not change the physical file path.
     *
     * @authenticated
     * @bodyParam name string required The new display name for the file. Example: final_report.pdf
     */
    public function rename(Request $request, Media $media): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:255']);
        
        try {
            $media = $this->mediaService->renameMedia($request->user(), $media, $request->input('name'));
            return response()->json(['success' => true, 'data' => $media]);
        } catch (MediaException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 403);
        }
    }

    /**
     * Update Visibility
     *
     * Changes the visibility of a media file (public, private, shared) and toggle download permissions.
     *
     * @authenticated
     * @bodyParam visibility string required The visibility level (public, private, shared). Example: shared
     * @bodyParam allow_download boolean optional Whether common users can download the file. Default: true. Example: false
     */
    public function updateVisibility(Request $request, Media $media): JsonResponse
    {
        $request->validate([
            'visibility' => 'required|in:public,private,shared',
            'allow_download' => 'nullable|boolean'
        ]);

        try {
            $media = $this->mediaService->updateVisibility(
                $request->user(), 
                $media, 
                $request->input('visibility'),
                $request->boolean('allow_download', true)
            );
            return response()->json(['success' => true, 'data' => $media]);
        } catch (MediaException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 403);
        }
    }

    /**
     * View Shared Media (Public)
     *
     * Access a media file via a secure share token without requiring authentication.
     *
     * @urlParam token string required The unique share token (UUID). Example: 550e8400-e29b-41d4-a716-446655440000
     */
    public function showShared(string $token): mixed
    {
        $media = Media::where('share_token', $token)->where('visibility', 'shared')->firstOrFail();
        
        $path = ltrim($media->file_path, '/');
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        if (!$media->allow_download) {
            // Serve inline (browser view only)
            return response()->file(Storage::disk('public')->path($path), [
                'Content-Type' => $media->mime_type,
                'Content-Disposition' => 'inline; filename="' . $media->file_name . '"'
            ]);
        }

        return Storage::disk('public')->download($path, $media->file_name);
    }

    /**
     * Initialize Multipart Upload
     *
     * @authenticated
     */
    public function initMultipart(Request $request): JsonResponse
    {
        $request->validate([
            'file_name' => 'required|string',
            'total_size' => 'required|integer',
            'mime_type' => 'required|string',
        ]);

        try {
            $result = $this->mediaService->initMultipartUpload(
                $request->user(),
                $request->input('file_name'),
                $request->input('total_size'),
                $request->input('mime_type')
            );
            return response()->json(['success' => true, 'data' => $result]);
        } catch (MediaException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Upload Chunk
     *
     * @authenticated
     */
    public function uploadChunk(Request $request, string $uploadId): JsonResponse
    {
        $request->validate([
            'chunk_index' => 'required|integer',
            'chunk' => 'required|file',
        ]);

        try {
            $this->mediaService->uploadChunk($uploadId, $request->input('chunk_index'), $request->file('chunk'));
            return response()->json(['success' => true]);
        } catch (MediaException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Complete Multipart Upload
     *
     * @authenticated
     */
    public function completeMultipart(Request $request, string $uploadId): JsonResponse
    {
        $request->validate([
            'file_name' => 'required|string',
            'mime_type' => 'required|string',
        ]);

        try {
            $media = $this->mediaService->completeMultipartUpload(
                $request->user(),
                $uploadId,
                $request->input('file_name'),
                $request->input('mime_type')
            );
            return response()->json(['success' => true, 'data' => $media]);
        } catch (MediaException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
