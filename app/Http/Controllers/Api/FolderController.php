<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FolderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * List folders for the user
     */
    public function index(Request $request): JsonResponse
    {
        $parentId = $request->query('parent_id');
        if ($parentId === 'root' || $parentId === 'null' || $parentId === '' || $parentId === null) {
            $parentId = null;
        } else {
            $parentId = (int) $parentId;
        }
        
        $rootType = $request->query('root_type', 'personal');

        $folders = Folder::where('user_id', $request->user()->id)
            ->where('root_type', $rootType)
            ->where('parent_id', $parentId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $folders
        ]);
    }

    /**
     * Create a new folder
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:folders,id',
            'root_type' => 'required|in:personal,public',
        ]);

        // Security check: parent must belong to user
        if ($request->parent_id) {
            $parent = Folder::findOrFail($request->parent_id);
            if ($parent->user_id !== $request->user()->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized parent folder'], 403);
            }
        }

        try {
            $folder = Folder::create([
                'user_id' => $request->user()->id,
                'parent_id' => $request->parent_id,
                'name' => $request->name,
                'root_type' => $request->root_type,
                'is_system' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Folder created successfully',
                'data' => $folder
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update/Rename folder
     */
    public function update(Request $request, Folder $folder): JsonResponse
    {
        $this->authorizeAccess($request->user(), $folder);

        if ($folder->is_system) {
            return response()->json(['success' => false, 'message' => 'System folders cannot be renamed'], 422);
        }

        $request->validate(['name' => 'required|string|max:255']);

        $folder->update(['name' => $request->name]);

        return response()->json([
            'success' => true,
            'message' => 'Folder renamed successfully',
            'data' => $folder
        ]);
    }

    /**
     * Rename folder by name and path context
     */
    public function renameByName(Request $request, \App\Services\Contracts\MediaServiceContract $mediaService): JsonResponse
    {
        $request->validate([
            'current_name' => 'required|string',
            'new_name' => 'required|string',
            'root_type' => 'required|in:personal,public',
            'parent_id' => 'nullable|exists:folders,id',
            'path' => 'nullable|array',
        ]);

        $parentId = $request->parent_id;

        // If path is provided, find/ensure the parent folder ID
        if ($request->has('path') && !empty($request->path)) {
            try {
                $parentFolder = $mediaService->ensureSubfolder(
                    $request->user(),
                    $request->root_type,
                    (array) $request->path
                );
                $parentId = $parentFolder->id;
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => 'Invalid path: ' . $e->getMessage()], 422);
            }
        }

        $folder = Folder::where('user_id', $request->user()->id)
            ->where('root_type', $request->root_type)
            ->where('parent_id', $parentId)
            ->where('name', $request->current_name)
            ->first();

        if (!$folder) {
            $context = $request->has('path') ? implode('/', $request->path) : ($parentId ? 'folder ID ' . $parentId : 'root');
            return response()->json([
                'success' => false, 
                'message' => 'Folder "' . $request->current_name . '" not found in ' . $context
            ], 404);
        }

        if ($folder->is_system) {
            return response()->json(['success' => false, 'message' => 'System folders cannot be renamed'], 422);
        }

        $folder->update(['name' => $request->new_name]);

        return response()->json([
            'success' => true,
            'message' => 'Folder renamed successfully',
            'data' => $folder
        ]);
    }

    /**
     * Delete folder (Recursive delete handled by DB constraints or manual logic)
     */
    public function destroy(Request $request, Folder $folder): JsonResponse
    {
        $this->authorizeAccess($request->user(), $folder);

        if ($folder->is_system) {
            return response()->json(['success' => false, 'message' => 'System folders cannot be deleted'], 422);
        }

        // Check if folder contains media or subfolders
        $hasContent = $folder->media()->exists() || $folder->children()->exists();
        if ($hasContent && !$request->boolean('force')) {
            return response()->json([
                'success' => false,
                'message' => 'Folder is not empty. Use force=true to delete everything inside.'
            ], 422);
        }

        $folder->delete();

        return response()->json([
            'success' => true,
            'message' => 'Folder deleted successfully'
        ]);
    }

    protected function authorizeAccess($user, Folder $folder)
    {
        if ($folder->user_id !== $user->id && !\App\Support\AdminAccessResolver::canAccessAdmin($user)) {
            abort(403, 'Unauthorized');
        }
    }
}
