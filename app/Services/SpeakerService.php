<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Speaker;
use App\Services\Contracts\SpeakerServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SpeakerService implements SpeakerServiceContract
{
    /**
     * List speakers for an event.
     */
    public function listSpeakers(Event $event): Collection
    {
        return $event->speakers()->orderBy('sort_order')->get();
    }

    /**
     * Add speaker to event.
     */
    public function addSpeaker(Event $event, array $data): Speaker
    {
        $speaker = $event->speakers()->create($data);

        if (!empty($data['photo_file'])) {
            $path = $data['photo_file']->store('events/speakers', 'public');
            $speaker->update(['photo_path' => $path]);
        }

        return $speaker->fresh();
    }

    /**
     * Update speaker.
     */
    public function updateSpeaker(Speaker $speaker, array $data): Speaker
    {
        $speaker->update($data);

        if (!empty($data['photo_file'])) {
            if ($speaker->photo_path) {
                Storage::disk('public')->delete($speaker->photo_path);
            }
            $path = $data['photo_file']->store('events/speakers', 'public');
            $speaker->update(['photo_path' => $path]);
        }

        return $speaker->fresh();
    }

    /**
     * Remove speaker.
     */
    public function removeSpeaker(Speaker $speaker): bool
    {
        try {
            if ($speaker->photo_path) {
                Storage::disk('public')->delete($speaker->photo_path);
            }
            return $speaker->delete();
        } catch (\Exception $e) {
            Log::error('Failed to remove speaker', ['error' => $e->getMessage(), 'speaker_id' => $speaker->id]);
            return false;
        }
    }
}
