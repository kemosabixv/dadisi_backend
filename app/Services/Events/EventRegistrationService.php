<?php

namespace App\Services\Events;

use App\Exceptions\EventCapacityExceededException;
use App\Exceptions\EventException;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Contracts\EventRegistrationServiceContract;
use App\Services\QrCodeService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EventRegistrationService
 *
 * Manages event registration workflows including registration, cancellation,
 * and bulk operations. Enforces capacity constraints.
 */
class EventRegistrationService implements EventRegistrationServiceContract
{
    public function __construct(
        private QrCodeService $qrCodeService
    ) {}

    /**
     * Register a user for an event
     *
     * @param Authenticatable $user The user registering
     * @param Event $event The event
     * @param array $data Additional data
     * @return EventRegistration The registration
     *
     * @throws EventException|EventCapacityExceededException
     */
    public function registerUser(Authenticatable $user, Event $event, array $data = []): EventRegistration
    {
        try {
            DB::beginTransaction();

            // Check if already registered
            $existing = EventRegistration::where('user_id', $user->getAuthIdentifier())
                ->where('event_id', $event->id)
                ->first();

            if ($existing && $existing->status === 'confirmed') {
                DB::rollBack();
                throw EventException::alreadyRegistered();
            }

            // Check capacity
            $confirmedCount = EventRegistration::where('event_id', $event->id)
                ->where('status', 'confirmed')
                ->count();

            if ($event->capacity !== null && $confirmedCount >= $event->capacity) {
                DB::rollBack();
                throw EventCapacityExceededException::eventAtCapacity();
            }

            $registration = EventRegistration::create([
                'user_id' => $user->getAuthIdentifier(),
                'event_id' => $event->id,
                'ticket_id' => $data['ticket_id'] ?? 1, // Default ticket
                'confirmation_code' => 'CONF-' . strtoupper(\Illuminate\Support\Str::random(10)),
                'status' => 'confirmed',
                'qr_code_token' => \Illuminate\Support\Str::random(32),
            ]);

            AuditLog::create([
                'actor_id' => $user->getAuthIdentifier(),
                'action' => 'registered_for_event',
                'model' => EventRegistration::class,
                'model_id' => $registration->id,
                'changes' => ['event_id' => $event->id],
            ]);

            Log::info("User registered for event", [
                'user_id' => $user->getAuthIdentifier(),
                'event_id' => $event->id,
                'registration_id' => $registration->id,
            ]);

            // Dispatch confirmation notification
            try {
                $user->notify(new \App\Notifications\EventRegistrationConfirmation($registration));
            } catch (\Exception $e) {
                Log::error('Failed to send event registration notification', [
                    'registration_id' => $registration->id,
                    'error' => $e->getMessage()
                ]);
            }

            DB::commit();

            // Generate QR code image immediately for the user
            try {
                $this->qrCodeService->generateQrCode($registration);
            } catch (\Exception $e) {
                // Log but don't fail registration if QR generation fails
                Log::warning('QR code generation failed after registration', [
                    'registration_id' => $registration->id,
                    'error' => $e->getMessage()
                ]);
            }

            return $registration;
        } catch (\Exception $e) {
            DB::rollBack();

            if ($e instanceof EventException || $e instanceof EventCapacityExceededException) {
                throw $e;
            }

            Log::error('Event registration failed', [
                'user_id' => $user->getAuthIdentifier(),
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            throw EventException::registrationFailed($e->getMessage());
        }
    }

    /**
     * Cancel a user's registration
     *
     * @param Authenticatable $user The user
     * @param Event $event The event
     * @param string|null $reason Cancellation reason
     * @return bool True if successful
     *
     * @throws EventException
     */
    public function cancelRegistration(Authenticatable $user, Event $event, ?string $reason = null): bool
    {
        try {
            return DB::transaction(function () use ($user, $event, $reason) {
                $registration = EventRegistration::where('user_id', $user->getAuthIdentifier())
                    ->where('event_id', $event->id)
                    ->first();

                if (!$registration) {
                    throw EventException::registrationNotFound();
                }

                if ($registration->status === 'attended') {
                    throw EventException::cancellationFailed("Cannot cancel an attended event");
                }

                $registration->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason
                ]);

                AuditLog::create([
                    'actor_id' => $user->getAuthIdentifier(),
                    'action' => 'cancelled_registration',
                    'model' => EventRegistration::class,
                    'model_id' => $registration->id,
                    'changes' => ['reason' => $reason],
                ]);

                Log::info('User registration cancelled', [
                    'registration_id' => $registration->id,
                    'user_id' => $user->getAuthIdentifier(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            if ($e instanceof EventException) {
                throw $e;
            }

            Log::error('Registration cancellation failed', [
                'user_id' => $user->getAuthIdentifier(),
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);

            throw EventException::cancellationFailed($e->getMessage());
        }
    }

    /**
     * Get a user's registration for an event
     *
     * @param Authenticatable $user The user
     * @param Event $event The event
     * @return EventRegistration|null The registration or null
     */
    public function getRegistration(Authenticatable $user, Event $event): ?EventRegistration
    {
        return EventRegistration::where('user_id', $user->getAuthIdentifier())
            ->where('event_id', $event->id)
            ->first();
    }

    /**
     * Check if user is registered for event
     *
     * @param Authenticatable $user The user
     * @param Event $event The event
     * @return bool True if registered
     */
    public function isRegistered(Authenticatable $user, Event $event): bool
    {
        return EventRegistration::where('user_id', $user->getAuthIdentifier())
            ->where('event_id', $event->id)
            ->where('status', 'confirmed')
            ->exists();
    }

    /**
     * Get all registrations for an event
     *
     * @param Event $event The event
     * @return Collection Registrations
     */
    public function getEventRegistrations(Event $event): Collection
    {
        return $event->registrations()->where('status', 'confirmed')->get();
    }

    /**
     * Get all events a user is registered for
     *
     * @param Authenticatable $user The user
     * @return Collection User's registrations
     */
    public function getUserRegistrations(Authenticatable $user): Collection
    {
        return EventRegistration::where('user_id', $user->getAuthIdentifier())
            ->where('status', 'confirmed')
            ->with('event')
            ->get();
    }

    /**
     * Get confirmed registration count
     *
     * @param Event $event The event
     * @return int Count
     */
    public function getConfirmedCount(Event $event): int
    {
        return EventRegistration::where('event_id', $event->id)
            ->where('status', 'confirmed')
            ->count();
    }

    /**
     * Bulk register users (max 50)
     *
     * @param Event $event The event
     * @param array $userIds User IDs
     * @param Authenticatable|null $actor The user performing the action
     * @return int Successful registrations
     *
     * @throws EventException|EventCapacityExceededException
     */
    public function bulkRegister(Event $event, array $userIds, ?Authenticatable $actor = null): int
    {
        if (count($userIds) > 50) {
            throw EventException::bulkOperationLimitExceeded(50);
        }

        try {
            DB::beginTransaction();

            $currentCount = $this->getConfirmedCount($event);
            
            if ($event->capacity !== null) {
                $availableSlots = $event->capacity - $currentCount;
                if (count($userIds) > $availableSlots) {
                    DB::rollBack();
                    throw EventCapacityExceededException::insufficientCapacity($availableSlots);
                }
            }

            $count = 0;
            foreach ($userIds as $userId) {
                $user = \App\Models\User::find($userId);
                if ($user && !$this->isRegistered($user, $event)) {
                    EventRegistration::create([
                        'user_id' => $userId,
                        'event_id' => $event->id,
                        'ticket_id' => 1, // Default ticket
                        'confirmation_code' => 'CONF-' . strtoupper(\Illuminate\Support\Str::random(10)),
                        'status' => 'confirmed',
                    ]);
                    $count++;
                }
            }

            AuditLog::create([
                'actor_id' => $actor?->getAuthIdentifier(),
                'action' => 'bulk_registered',
                'model' => Event::class,
                'model_id' => $event->id,
                'changes' => ['count' => $count],
            ]);

            DB::commit();

            return $count;
        } catch (\Exception $e) {
            DB::rollBack();

            if ($e instanceof EventException || $e instanceof EventCapacityExceededException) {
                throw $e;
            }

            throw EventException::bulkRegistrationFailed($e->getMessage());
        }
    }

    /**
     * Bulk cancel registrations (max 50)
     *
     * @param Event $event The event
     * @param array $userIds User IDs
     * @param Authenticatable|null $actor The user performing the action
     * @return int Cancellations
     *
     * @throws EventException
     */
    public function bulkCancel(Event $event, array $userIds, ?Authenticatable $actor = null): int
    {
        if (count($userIds) > 50) {
            throw EventException::bulkOperationLimitExceeded(50);
        }

        try {
            DB::beginTransaction();

            $count = EventRegistration::where('event_id', $event->id)
                ->whereIn('user_id', $userIds)
                ->where('status', 'confirmed')
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);

            AuditLog::create([
                'actor_id' => $actor?->getAuthIdentifier(),
                'action' => 'bulk_cancelled',
                'model' => Event::class,
                'model_id' => $event->id,
                'changes' => ['count' => $count],
            ]);

            DB::commit();

            return $count;
        } catch (\Exception $e) {
            DB::rollBack();

            throw EventException::bulkCancellationFailed($e->getMessage());
        }
    }

    /**
     * Check in a user using a QR code token
     */
    public function checkIn(string $qrCodeToken): EventRegistration
    {
        $registration = EventRegistration::where('qr_code_token', $qrCodeToken)
            ->with(['event', 'user', 'ticket'])
            ->first();

        if (!$registration) {
            throw EventException::registrationNotFound("Invalid or unknown ticket token.");
        }

        if ($registration->status === 'cancelled') {
            throw EventException::updateFailed("This registration has been cancelled.");
        }

        if ($registration->status === 'attended' || $registration->check_in_at !== null) {
            throw EventException::updateFailed("This ticket has already been used for check-in at {$registration->check_in_at->format('Y-m-d H:i')}.");
        }

        $registration->update([
            'status' => 'attended',
            'check_in_at' => now(),
        ]);

        AuditLog::create([
            'actor_id' => auth()->id(),
            'action' => 'checked_in_attendee',
            'model' => EventRegistration::class,
            'model_id' => $registration->id,
            'changes' => ['old_status' => 'confirmed', 'new_status' => 'attended'],
        ]);

        return $registration;
    }
}
