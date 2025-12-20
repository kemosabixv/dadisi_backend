<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpeakerResource;
use App\Models\Event;
use App\Models\Speaker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class SpeakerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index']);
    }

    /**
     * List Speakers for Event
     * 
     * @group Speakers
     */
    public function index(Event $event)
    {
        $speakers = $event->speakers()->orderBy('sort_order')->get();
        return SpeakerResource::collection($speakers);
    }

    /**
     * Add Speaker to Event
     * 
     * @group Speakers
     */
    public function store(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'website_url' => 'nullable|url',
            'linkedin_url' => 'nullable|url',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $speaker = $event->speakers()->create($validated);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('events/speakers', 'public');
            $speaker->update(['photo_path' => $path]);
        }

        return new SpeakerResource($speaker);
    }

    /**
     * Update Speaker
     * 
     * @group Speakers
     */
    public function update(Request $request, Speaker $speaker)
    {
        $this->authorize('update', $speaker->event);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'website_url' => 'nullable|url',
            'linkedin_url' => 'nullable|url',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $speaker->update($validated);

        if ($request->hasFile('photo')) {
            if ($speaker->photo_path) {
                Storage::disk('public')->delete($speaker->photo_path);
            }
            $path = $request->file('photo')->store('events/speakers', 'public');
            $speaker->update(['photo_path' => $path]);
        }

        return new SpeakerResource($speaker);
    }

    /**
     * Remove Speaker
     * 
     * @group Speakers
     */
    public function destroy(Speaker $speaker)
    {
        $this->authorize('update', $speaker->event);
        
        if ($speaker->photo_path) {
            Storage::disk('public')->delete($speaker->photo_path);
        }
        
        $speaker->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
