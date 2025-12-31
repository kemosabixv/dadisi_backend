<?php

namespace App\Services\Contracts;

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
    public function addSpeaker(Event $event, array $data): Speaker;

    /**
     * Update speaker.
     */
    public function updateSpeaker(Speaker $speaker, array $data): Speaker;

    /**
     * Remove speaker.
     */
    public function removeSpeaker(Speaker $speaker): bool;
}
