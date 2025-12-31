<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpeakerResource;
use App\Models\Event;
use App\Models\Speaker;
use App\Services\Contracts\SpeakerServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SpeakerController extends Controller
{
    public function __construct(private SpeakerServiceContract $speakerService)
    {
        $this->middleware('auth:sanctum')->except(['index']);
    }

    /**
     * List Speakers for Event
     *
     * @group Speakers
     * @unauthenticated
     * @urlParam event integer required The event ID. Example: 5
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Dr. Sarah Kimani",
     *       "company": "KEMRI",
     *       "designation": "Chief Researcher",
     *       "photo_url": "https://api.dadisilab.com/storage/events/speakers/sarah.jpg"
     *     }
     *   ]
     * }
     */
    public function index(Event $event): JsonResponse
    {
        try {
            $speakers = $this->speakerService->listSpeakers($event);
            return response()->json(['success' => true, 'data' => SpeakerResource::collection($speakers)]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve speakers', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve speakers'], 500);
        }
    }

    /**
     * Add Speaker to Event
     * 
     * @group Speakers
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        try {
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

            if ($request->hasFile('photo')) {
                $validated['photo_file'] = $request->file('photo');
            }

            $speaker = $this->speakerService->addSpeaker($event, $validated);

            return response()->json([
                'success' => true,
                'data' => new SpeakerResource($speaker),
                'message' => 'Speaker added successfully.'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create speaker', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['success' => false, 'message' => 'Failed to create speaker'], 500);
        }
    }

    /**
     * Update Speaker
     * 
     * @group Speakers
     */
    public function update(Request $request, Speaker $speaker): JsonResponse
    {
        try {
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

            if ($request->hasFile('photo')) {
                $validated['photo_file'] = $request->file('photo');
            }

            $updatedSpeaker = $this->speakerService->updateSpeaker($speaker, $validated);

            return response()->json([
                'success' => true,
                'data' => new SpeakerResource($updatedSpeaker),
                'message' => 'Speaker updated successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update speaker', ['error' => $e->getMessage(), 'speaker_id' => $speaker->id]);
            return response()->json(['success' => false, 'message' => 'Failed to update speaker'], 500);
        }
    }

    /**
     * Remove Speaker
     * 
     * @group Speakers
     */
    public function destroy(Speaker $speaker): JsonResponse
    {
        try {
            $this->authorize('update', $speaker->event);
            
            $this->speakerService->removeSpeaker($speaker);

            return response()->json(['success' => true], Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            Log::error('Failed to delete speaker', ['error' => $e->getMessage(), 'speaker_id' => $speaker->id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete speaker'], 500);
        }
    }
}
