<?php

namespace App\Services\Contracts;

use App\DTOs\CreateSpeakerDTO;
use App\DTOs\UpdateSpeakerDTO;
use App\Models\Event;
use App\Models\Speaker;
use Illuminate\Support\Collection;

interface SpeakerServiceContract
{
    /**
     * List speakers for an event.
     */
    public function listSpeakers(Event $event): Collection;

    /**
     * Add speaker to event.
     */
    public function addSpeaker(Event $event, CreateSpeakerDTO $dto): Speaker;

    /**
     * Update speaker.
     */
    public function updateSpeaker(Speaker $speaker, UpdateSpeakerDTO $dto): Speaker;

    /**
     * Remove speaker.
     */
    public function removeSpeaker(Speaker $speaker): bool;
}
