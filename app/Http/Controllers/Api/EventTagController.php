<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventTagResource;
use App\Models\EventTag;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EventTagController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'admin'])->except(['index']);
    }

    /**
     * List All Tags
     * 
     * @group Event Tags
     */
    public function index()
    {
        return EventTagResource::collection(EventTag::all());
    }

    /**
     * Create Tag (Admin Only)
     * 
     * @group Event Tags
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:event_tags,slug',
        ]);

        $tag = EventTag::create($validated);

        return new EventTagResource($tag);
    }

    /**
     * Delete Tag (Admin Only)
     * 
     * @group Event Tags
     */
    public function destroy(EventTag $tag)
    {
        $tag->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
