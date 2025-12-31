<?php

namespace App\Services\Events;

use App\Exceptions\EventException;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Contracts\EventCapacityServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * EventCapacityService
 *
 * Manages event capacity including checks, updates, and reservations.
 * Ensures capacity constraints are maintained across registrations.
 */
class EventCapacityService implements EventCapacityServiceContract
{
    /**
     * Check if event has available capacity
     *
     * @param Event $event The event
     * @param int $slots Slots needed
     * @return bool True if capacity available
     */
    public function hasCapacity(Event $event, int $slots = 1): bool
    {
        $available = $this->getAvailableCapacity($event);
        return $available >= $slots;
    }

    /**
     * Get available capacity
     *
     * @param Event $event The event
     * @return int Available slots
     */
    public function getAvailableCapacity(Event $event): int
    {
        $confirmed = $this->getAttendeeCount($event);
        return max(0, $event->capacity - $confirmed);
    }

    /**
     * Get total capacity
     *
     * @param Event $event The event
     * @return int Total capacity
     */
    public function getTotalCapacity(Event $event): int
    {
        return $event->capacity;
    }

    /**
     * Get attendee count
     *
     * @param Event $event The event
     * @return int Number of confirmed attendees
     */
    public function getAttendeeCount(Event $event): int
    {
        return EventRegistration::where('event_id', $event->id)
            ->where('status', 'confirmed')
            ->count();
    }

    /**
     * Update event capacity
     *
     * @param Authenticatable $actor The user updating
     * @param Event $event The event
     * @param int $newCapacity New capacity
     * @return Event Updated event
     *
     * @throws EventException If new capacity < attendees
     */
    public function updateCapacity(Authenticatable $actor, Event $event, int $newCapacity): Event
    {
        try {
            $currentAttendees = $this->getAttendeeCount($event);

            if ($newCapacity < $currentAttendees) {
                throw EventException::capacityBelowAttendeeCount();
            }

            $oldCapacity = $event->capacity;
            $event->update(['capacity' => $newCapacity]);

            AuditLog::create([
                'actor_id' => $actor->getAuthIdentifier(),
                'action' => 'updated_capacity',
                'model' => Event::class,
                'model_id' => $event->id,
                'changes' => [
                    'old_capacity' => $oldCapacity,
                    'new_capacity' => $newCapacity,
                ],
            ]);

            Log::info("Event capacity updated", [
                'actor_id' => $actor->getAuthIdentifier(),
                'event_id' => $event->id,
                'old' => $oldCapacity,
                'new' => $newCapacity,
            ]);

            return $event->fresh();
        } catch (\Exception $e) {
            if ($e instanceof EventException) {
                throw $e;
            }

            Log::error('Capacity update failed', [
                'actor_id' => $actor->getAuthIdentifier(),
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            throw EventException::capacityUpdateFailed($e->getMessage());
        }
    }

    /**
     * Reserve capacity
     *
     * @param Event $event The event
     * @param int $slots Slots to reserve
     * @return bool True if successful
     *
     * @throws \App\Exceptions\EventCapacityExceededException
     */
    public function reserveCapacity(Event $event, int $slots): bool
    {
        if (!$this->hasCapacity($event, $slots)) {
            $remaining = $this->getAvailableCapacity($event);
            throw \App\Exceptions\EventCapacityExceededException::insufficientCapacity($remaining);
        }

        // In a real scenario, you might track reservations separately
        // For now, we just verify capacity exists
        return true;
    }

    /**
     * Release reserved capacity
     *
     * @param Event $event The event
     * @param int $slots Slots to release
     * @return bool True if successful
     */
    public function releaseCapacity(Event $event, int $slots): bool
    {
        // In a real scenario, decrement reserved count
        // For now, just return true
        return true;
    }

    /**
     * Check if at capacity
     *
     * @param Event $event The event
     * @return bool True if at capacity
     */
    public function isAtCapacity(Event $event): bool
    {
        return $this->getAvailableCapacity($event) === 0;
    }

    /**
     * Get utilization percentage
     *
     * @param Event $event The event
     * @return float Percentage (0-100)
     */
    public function getUtilization(Event $event): float
    {
        if ($event->capacity === 0) {
            return 0.0;
        }

        $attendees = $this->getAttendeeCount($event);
        return ($attendees / $event->capacity) * 100;
    }
}
