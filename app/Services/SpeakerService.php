<?php

namespace App\Services;

use App\DTOs\CreateSpeakerDTO;
use App\DTOs\UpdateSpeakerDTO;
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
    public function addSpeaker(Event $event, CreateSpeakerDTO $dto): Speaker
    {
        $data = $dto->toArray();
        $speaker = $event->speakers()->create($data);

        // Only use CAS media_id for photo
        if (!empty($data['photo_media_id'])) {
            $speaker->setPhotoMedia($data['photo_media_id']);
        }

        return $speaker->fresh();
    }

    /**
     * Update speaker.
     */
    public function updateSpeaker(Speaker $speaker, UpdateSpeakerDTO $dto): Speaker
    {
        $data = array_filter($dto->toArray(), fn($v) => $v !== null);
        $speaker->update($data);

        // Only use CAS media_id for photo
        if (!empty($data['photo_media_id'])) {
            $speaker->setPhotoMedia($data['photo_media_id']);
        }

        return $speaker->fresh();
    }

    /**
     * Remove speaker.
     */
    public function removeSpeaker(Speaker $speaker): bool
    {
        try {
            // Only detach CAS media if needed (handled by model hooks if implemented)
            return $speaker->delete();
        } catch (\Exception $e) {
            Log::error('Failed to remove speaker', ['error' => $e->getMessage(), 'speaker_id' => $speaker->id]);
            return false;
        }
    }
}
