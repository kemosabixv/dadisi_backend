<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MediaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\Contracts\MediaServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MediaController extends Controller
{
    public function __construct(
        private MediaServiceContract $mediaService
    ) {
        $this->middleware('auth')->except(['showShared']);
    }

    /**
     * Get User Storage Quota
     */
    public function quota(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->mediaService->getStorageQuota($request->user()),
        ]);
    }

    /**
     * List Media (with folder filtering)
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['type', 'search', 'folder_id', 'root_type', 'visibility']);
        $perPage = min((int) $request->input('per_page', 15), 100);

        // Handle root folder filtering
        $folderId = $request->input('folder_id');
        if ($folderId === 'root' || $folderId === 'null' || $folderId === '' || $folderId === null) {
            $filters['folder_id'] = null;
        } else {
            $filters['folder_id'] = (int) $folderId;
        }

        $media = $this->mediaService->listMedia($request->user(), $filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => MediaResource::collection($media),
            'pagination' => [
                'total' => $media->total(),
                'per_page' => $media->perPage(),
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
            ],
        ]);
    }

    /**
     * Upload Media
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        try {
            $media = $this->mediaService->uploadMedia(
                $request->user(),
                $request->file('file'),
                $request->only(['folder_id', 'root_type', 'visibility', 'temporary', 'path'])
            );

            return (new MediaResource($media))->additional([
                'success' => true,
                'message' => 'Media uploaded successfully',
            ])->response()->setStatusCode(201);
        } catch (MediaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['file' => [$e->getMessage()]],
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Media upload failed',
                'errors' => ['file' => [$e->getMessage()]],
            ], 422);
        }
    }

    /**
     * Show Media details
     */
    public function show(Request $request, Media $media): JsonResponse
    {
        $this->authorizeAccess($request->user(), $media);
        return (new MediaResource($media))->additional(['success' => true])->response();
    }

    /**
     * Update Media (Rename, Visibility, Move)
     */
    public function update(Request $request, Media $media): JsonResponse
    {
        $this->authorizeAccess($request->user(), $media);

        try {
            if ($request->has('file_name')) {
                $this->mediaService->renameMedia($request->user(), $media, $request->input('file_name'));
            }

            if ($request->has('visibility')) {
                $this->mediaService->updateVisibility(
                    $request->user(),
                    $media,
                    $request->input('visibility'),
                    $request->boolean('allow_download', true),
                    $request->input('expires_at')
                );
            }

            if ($request->has('folder_id')) {
                $this->mediaService->moveMedia($request->user(), $media, $request->input('folder_id'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Media updated successfully',
                'data' => new MediaResource($media->fresh(['file', 'folder']))
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete Media (CAS safety check included in Service)
     */
    public function destroy(Request $request, Media $media): JsonResponse
    {
        $this->authorizeAccess($request->user(), $media);

        try {
            $this->mediaService->deleteMedia($request->user(), $media);

            return response()->json([
                'success' => true,
                'message' => 'Media deleted successfully',
            ]);
        } catch (MediaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['media' => [$e->getMessage()]],
                'attachments' => $media->getAttachments(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Media deletion failed',
                'attachments' => $media->getAttachments(),
            ], 422);
        }
    }

    /**
     * Bulk Move Media
     */
    public function bulkMove(Request $request): JsonResponse
    {
        $request->validate([
            'media_ids' => 'required|array',
            'media_ids.*' => 'exists:media,id',
            'folder_id' => 'nullable|exists:folders,id',
            'root_type' => 'nullable|string|in:personal,public',
        ]);

        $success = 0;
        foreach ($request->media_ids as $id) {
            try {
                $media = Media::findOrFail($id);
                $this->mediaService->moveMedia(
                    $request->user(),
                    $media,
                    $request->input('folder_id'),
                    $request->input('root_type')
                );
                $success++;
            } catch (\Exception $e) {
                Log::warning("Bulk move failed for media {$id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully moved {$success} items.",
        ]);
    }

    /**
     * Multipart Upload Methods
     */
    public function initMultipart(Request $request): JsonResponse
    {
        $request->validate([
            'file_name' => 'required|string',
            'total_size' => 'required|integer',
            'mime_type' => 'required|string',
        ]);

        $data = $this->mediaService->initMultipartUpload(
            $request->user(),
            $request->input('file_name'),
            $request->input('total_size'),
            $request->input('mime_type'),
            $request->only(['folder_id', 'root_type', 'visibility', 'path', 'skip_quota'])
        );

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function uploadChunk(Request $request, string $uploadId): JsonResponse
    {
        $request->validate([
            'chunk' => 'required|file',
            'chunk_index' => 'required|integer',
        ]);

        $success = $this->mediaService->uploadChunk(
            $uploadId,
            $request->input('chunk_index'),
            $request->file('chunk')
        );

        return response()->json([
            'success' => $success
        ]);
    }

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
                $request->input('mime_type'),
                $request->only(['folder_id', 'root_type', 'visibility', 'path', 'skip_quota'])
            );

            return (new MediaResource($media))->additional([
                'success' => true,
                'message' => 'Multipart upload completed'
            ])->response();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get shared media info (Public)
     */
    public function getSharedInfo(string $token): JsonResponse
    {
        $media = Media::where('share_token', $token)
            ->where('visibility', 'shared')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->firstOrFail();

        return (new MediaResource($media))->additional(['success' => true])->response();
    }

    /**
     * Show shared media via token (Public)
     */
    public function showShared(string $token): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $media = Media::where('share_token', $token)
            ->where('visibility', 'shared')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->firstOrFail();

        return $this->mediaService->streamMedia($media);
    }

    /**
     * Internal access check
     */
    protected function authorizeAccess($user, Media $media)
    {
        if ($media->user_id !== $user->id && !\App\Support\AdminAccessResolver::canAccessAdmin($user)) {
            abort(403, 'Unauthorized');
        }
    }
}
