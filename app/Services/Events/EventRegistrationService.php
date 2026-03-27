<?php

namespace App\Services\Events;

use App\Exceptions\EventCapacityExceededException;
use App\Exceptions\EventException;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventOrder;
use App\Services\Contracts\EventRegistrationServiceContract;
use App\Services\QrCodeService;
use App\Notifications\EventWaitlistPromoted;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Services\SystemSettingService;

/**
 * EventRegistrationService
 *
 * Manages event registration workflows including registration, cancellation,
 * and bulk operations. Enforces capacity constraints.
 */
class EventRegistrationService implements EventRegistrationServiceContract
{
    public function __construct(
        private QrCodeService $qrCodeService,
        private \App\Services\Contracts\RefundServiceContract $refundService
    ) {}

    /**
     * Remove a user (or guest) from an event waitlist
     */
    public function leaveWaitlist(string $identifier, Event $event): bool
    {
        $registration = EventRegistration::where('event_id', $event->id)
            ->where('status', 'waitlisted')
            ->where(function ($q) use ($identifier) {
                $q->where('user_id', $identifier)
                  ->orWhere('guest_email', $identifier)
                  ->orWhere('confirmation_code', $identifier)
                  ->orWhere('qr_code_token', $identifier);
            })
            ->first();

        if (!$registration) {
            return false;
        }

        return $registration->update(['status' => 'cancelled', 'cancellation_reason' => 'Left waitlist']);
    }

    /**
     * Check if user has waitlist priority
     */
    protected function hasWaitlistPriority(?Authenticatable $user): bool
    {
        if (!$user instanceof \App\Models\User) {
            return false;
        }

        $subscription = $user->activeSubscription()->first();
        if (!$subscription || !$subscription->plan) {
            return false;
        }

        return (bool) $subscription->plan->getFeatureValue('waitlist_priority', false);
    }

    /**
     * Register a user (or guest) for an event
     *
     * @param Authenticatable|null $user The user registering
     * @param Event $event The event
     * @param array $data Additional data (e.g., ticket_id, guest_name, guest_email)
     * @return EventRegistration The registration
     *
     * @throws EventException|EventCapacityExceededException
     */
    public function registerUser(?Authenticatable $user, Event $event, array $data = [], bool $isWaitlistAction = false): EventRegistration
    {
        $isRaceCondition = false;
        try {
            DB::beginTransaction();

            $userId = $user?->getAuthIdentifier();
            $guestEmail = $data['guest_email'] ?? $data['email'] ?? null;
            $guestName = $data['guest_name'] ?? $data['name'] ?? null;

            // Check if already registered
            if ($userId) {
                $existing = EventRegistration::where('user_id', $userId)
                    ->where('event_id', $event->id)
                    ->first();

                if ($existing && $existing->status === 'confirmed') {
                    DB::rollBack();
                    throw EventException::alreadyRegistered();
                }
            } elseif ($guestEmail) {
                $existing = EventRegistration::where('guest_email', $guestEmail)
                    ->where('event_id', $event->id)
                    ->first();

                if ($existing && $existing->status === 'confirmed') {
                    DB::rollBack();
                    throw EventException::alreadyRegistered();
                }
            }

            // Capacity check and waitlist handling
            $isFull = false;
            
            // 1. Check Ticket Tier Capacity
            if (isset($data['ticket_id'])) {
                $ticket = \App\Models\Ticket::find($data['ticket_id']);
                if ($ticket && $ticket->quantity !== null && $ticket->available <= 0) {
                    $isFull = true;
                }
            }

            // 2. Check Global Event Capacity
            if (!$isFull && $event->capacity !== null) {
                $confirmedCount = $this->getGlobalConfirmedCount($event);
                
                if ($confirmedCount >= $event->capacity) {
                    $isFull = true;
                }
            }

            $status = 'confirmed';
            $waitlistPosition = null;

            if ($isFull) {
                if (!$event->waitlist_enabled) {
                    DB::rollBack();
                    throw EventCapacityExceededException::eventAtCapacity();
                }

                $status = 'waitlisted';
                $isRaceCondition = !$isWaitlistAction;

                // FIFO Position Calculation with Priority:
                // Priority users: Range 1 - 999,999
                // Normal users: Range 1,000,000+
                $hasPriority = $this->hasWaitlistPriority($user);

                if ($hasPriority) {
                    $maxPriority = EventRegistration::where('event_id', $event->id)
                        ->where('waitlist_position', '<', 1000000)
                        ->max('waitlist_position') ?: 0;
                    $waitlistPosition = min($maxPriority + 1, 999999);
                } else {
                    $maxNormal = EventRegistration::where('event_id', $event->id)
                        ->where('waitlist_position', '>=', 1000000)
                        ->max('waitlist_position') ?: 999999;
                    $waitlistPosition = $maxNormal + 1;
                }
            }

            $registration = EventRegistration::create([
                'user_id' => $userId,
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'event_id' => $event->id,
                'ticket_id' => $data['ticket_id'] ?? 1, // Default ticket
                'confirmation_code' => 'CONF-' . strtoupper(\Illuminate\Support\Str::random(10)),
                'status' => $status,
                'waitlist_position' => $waitlistPosition,
                'qr_code_token' => \Illuminate\Support\Str::random(32),
            ]);

            // If confirmed, decrement ticket availability
            if ($status === 'confirmed') {
                $ticket = $registration->ticket;
                if ($ticket && $ticket->quantity !== null) {
                    $ticket->decrement('available');
                }
            }

            if ($userId) {
                AuditLog::create([
                    'actor_id' => $userId,
                    'action' => $status === 'waitlisted' ? 'joined_waitlist' : 'registered_for_event',
                    'model' => EventRegistration::class,
                    'model_id' => $registration->id,
                    'changes' => ['event_id' => $event->id, 'status' => $status, 'position' => $waitlistPosition],
                ]);
            }

            Log::info("User/Guest registration processed", [
                'user_id' => $userId,
                'guest_email' => $guestEmail,
                'event_id' => $event->id,
                'registration_id' => $registration->id,
                'status' => $status,
                'position' => $waitlistPosition,
            ]);

            // Generate QR code image immediately BEFORE notification
            try {
                $this->qrCodeService->generateQrCode($registration);
                $registration->refresh(); // Ensure we have the qr_code_path
            } catch (\Exception $e) {
                Log::warning('QR code generation failed after registration', [
                    'registration_id' => $registration->id,
                    'error' => $e->getMessage()
                ]);
            }

            DB::commit();

            // Dispatch confirmation notification
            try {
                if ($status === 'confirmed') {
                    if ($user) {
                        $user->notify(new \App\Notifications\EventRegistrationConfirmation($registration));
                    } elseif ($guestEmail) {
                        \Illuminate\Support\Facades\Mail::to($guestEmail)->send(
                            new \App\Mail\GuestEventTicket($registration)
                        );
                    }
                } else {
                    // Waitlist notification
                    if ($user) {
                        $user->notify(new \App\Notifications\EventWaitlistJoined($registration));
                    } elseif ($guestEmail) {
                        \Illuminate\Support\Facades\Notification::route('mail', $guestEmail)
                            ->notify(new \App\Notifications\EventWaitlistJoined($registration));
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send event notification', [
                    'registration_id' => $registration->id,
                    'error' => $e->getMessage()
                ]);
            }


            $registration->is_race_condition = $isRaceCondition;
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
     * @param EventRegistration|null $registration Specific registration instance (optional)
     * @param string|null $customerNotes Customer notes for refund request (optional)
     * @return bool True if successful
     *
     * @throws EventException
     */
    public function cancelRegistration(?Authenticatable $user, Event $event, ?string $reason = null, ?EventRegistration $registration = null, ?string $customerNotes = null): bool
    {
        try {
            return DB::transaction(function () use ($user, $event, $reason, $registration, $customerNotes) {
                if ($registration) {
                    if ($registration->event_id !== $event->id) {
                        throw EventException::registrationNotFound();
                    }
                } else {
                    if (!$user) {
                        throw EventException::registrationNotFound();
                    }
                    $registration = EventRegistration::where('event_id', $event->id)
                        ->where('user_id', $user->getAuthIdentifier())
                        ->where('status', '!=', 'cancelled')
                        ->first();
                }

                if (!$registration) {
                    throw EventException::registrationNotFound();
                }

                if ($registration->status === 'attended') {
                    throw EventException::cancellationFailed("Cannot cancel an attended event");
                }

                // Deadline check for non-staff
                $isStaff = $user && \App\Support\AdminAccessResolver::canAccessAdmin($user);
                if (!$isStaff) {
                    $deadlineDays = (int) app(SystemSettingService::class)->get('event_cancellation_deadline_days', 7);
                    if ($event->starts_at && now()->addDays($deadlineDays)->isAfter($event->starts_at)) {
                        throw EventException::cancellationFailed("Cancellations for refunds/transfers are only allowed up to {$deadlineDays} days before the event starts.");
                    }
                }

                $oldStatus = $registration->status;
                $registration->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason
                ]);

                // If it was confirmed OR pending-promoted, increment ticket availability
                $isPromoted = $registration->waitlist_position === -1 || ($registration->order && $registration->order->waitlist_position === -1);
                if ($oldStatus === 'confirmed' || ($oldStatus === 'pending' && $isPromoted)) {
                    $ticket = $registration->ticket;
                    if ($ticket && $ticket->quantity !== null) {
                        $ticket->increment('available');
                    }
                }

                // If it was confirmed and paid, trigger refund request
                if ($oldStatus === 'confirmed' && $registration->order && $registration->order->isPaid()) {
                    try {
                        $refund = $this->refundService->requestEventOrderRefund(
                            $registration->order,
                            $reason ?: 'Account cancellation',
                            $customerNotes
                        );

                        // Mark the order as cancelled immediately to free up global capacity
                        $registration->order->update(['status' => 'cancelled']);
                        
                        // Notify staff of new request
                        $staff = \App\Models\User::permission('manage_refunds')->get();
                        \Illuminate\Support\Facades\Notification::send(
                            $staff, 
                            new \App\Notifications\RefundRequestSubmitted($refund)
                        );

                        // Notify payer of new request (User or Guest) acknowledgement
                        if ($registration->user) {
                            $registration->user->notify(new \App\Notifications\RefundRequestSubmittedToPayer($refund));
                        } elseif ($registration->guest_email) {
                            \Illuminate\Support\Facades\Notification::route('mail', $registration->guest_email)
                                ->notify(new \App\Notifications\RefundRequestSubmittedToPayer($refund));
                        }
                    } catch (\Exception $re) {
                        Log::warning('Automatic refund request failed during cancellation', [
                            'registration_id' => $registration->id,
                            'error' => $re->getMessage()
                        ]);
                    }
                }

                AuditLog::create([
                    'actor_id' => $user?->getAuthIdentifier(),
                    'action' => 'cancelled_registration',
                    'model_type' => EventRegistration::class,
                    'model_id' => $registration->id,
                    'changes' => ['reason' => $reason, 'old_status' => $oldStatus],
                ]);

                Log::info('Registration cancelled', [
                    'registration_id' => $registration->id,
                    'user_id' => $user?->getAuthIdentifier(),
                    'guest_email' => $registration->guest_email,
                ]);

                // Promotion Logic: A spot opened up!
                $this->promoteWaitlistEntries($event);

                // Send Notification to User/Guest
                try {
                    if ($registration->user) {
                        $registration->user->notify(new \App\Notifications\EventRegistrationCancelled($registration, $reason));
                    } elseif ($registration->guest_email) {
                        \Illuminate\Support\Facades\Notification::route('mail', $registration->guest_email)
                            ->notify(new \App\Notifications\EventRegistrationCancelled($registration, $reason));
                    }
                } catch (\Exception $ne) {
                    Log::warning('Failed to send cancellation notification', [
                        'registration_id' => $registration->id,
                        'error' => $ne->getMessage()
                    ]);
                }

                return true;
            });
        } catch (\Exception $e) {
            if ($e instanceof EventException) {
                throw $e;
            }

            Log::error('Registration cancellation failed', [
                'user_id' => $user?->getAuthIdentifier(),
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
            ->whereIn('status', ['confirmed', 'attended'])
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
     * @param array $registrationIds Registration IDs
     * @param Authenticatable|null $actor The user performing the action
     * @param string|null $reason Optional reason for cancellation
     * @return int Cancellations
     *
     * @throws EventException
     */
    public function bulkCancel(Event $event, array $registrationIds, ?Authenticatable $actor = null, ?string $reason = null): int
    {
        if (count($registrationIds) > 50) {
            throw EventException::bulkOperationLimitExceeded(50);
        }

        try {
            return DB::transaction(function () use ($event, $registrationIds, $actor, $reason) {
                $registrations = EventRegistration::where('event_id', $event->id)
                    ->whereIn('id', $registrationIds)
                    ->where('status', '!=', 'cancelled')
                    ->get();

                $count = 0;
                foreach ($registrations as $reg) {
                    $oldStatus = $reg->status;
                    $reg->update([
                        'status' => 'cancelled',
                        'cancellation_reason' => $reason ?? ($actor ? "Staff generated by " . $actor->username : "Bulk cancellation")
                    ]);

                    // Trigger refund if needed (consistent with single cancel)
                    if ($oldStatus === 'confirmed' && $reg->order && $reg->order->isPaid()) {
                        try {
                            $this->refundService->requestEventOrderRefund($reg->order, $reason ?? 'Bulk cancellation');
                        } catch (\Exception $re) {
                            Log::warning('Bulk refund request failed', ['registration_id' => $reg->id]);
                        }
                    }

                    AuditLog::create([
                        'actor_id' => $actor?->getAuthIdentifier(),
                        'action' => 'cancelled_registration',
                        'model_type' => EventRegistration::class,
                        'model_id' => $reg->id,
                        'changes' => ['reason' => $reason, 'old_status' => $oldStatus],
                    ]);

                    $count++;
                }

                if ($count > 0) {
                    AuditLog::create([
                        'actor_id' => $actor?->getAuthIdentifier(),
                        'action' => 'bulk_cancelled_registrations',
                        'model_type' => Event::class,
                        'model_id' => $event->id,
                        'changes' => ['count' => $count],
                    ]);

                    // Promotion Logic: Spots opened up!
                    $this->promoteWaitlistEntries($event);
                }

                return $count;
            });
        } catch (\Exception $e) {
            Log::error('Bulk cancellation failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);

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

    /**
     * Promote waitlisted entries for a given event if capacity allows.
     * This considers both RSVPs (EventRegistration) and Paid Orders (EventOrder).
     *
     * @param Event $event
     * @return int Number of promoted entries
     */
    public function promoteWaitlistEntries(Event $event): int
    {
        $promotedCount = 0;

        try {
            return DB::transaction(function () use ($event, &$promotedCount) {
                // Fetch all waitlisted registrations (independent RSVPs only) and orders
                $registrations = EventRegistration::where('event_id', $event->id)
                    ->where('status', 'waitlisted')
                    ->whereNull('order_id')
                    ->get();
                
                $orders = EventOrder::where('event_id', $event->id)
                    ->where('status', 'waitlisted')
                    ->get();

                // Unify and sort by position, then by creation time
                $allEntries = $registrations->map(fn($item) => [
                        'type' => 'registration', 
                        'model' => $item, 
                        'position' => $item->waitlist_position, 
                        'created_at' => $item->created_at
                    ])
                    ->concat($orders->map(fn($item) => [
                        'type' => 'order', 
                        'model' => $item, 
                        'position' => $item->waitlist_position, 
                        'created_at' => $item->created_at
                    ]))
                    ->sortBy(['position', 'created_at']);

                foreach ($allEntries as $entry) {
                    $model = $entry['model'];
                    $ticket = $model->ticket;
                    $qtyNeeded = ($entry['type'] === 'order') ? $model->quantity : 1;

                    // 1. Check Global Capacity
                    $currentConfirmed = $this->getGlobalConfirmedCount($event);

                    if ($event->capacity !== null && ($currentConfirmed + $qtyNeeded) > $event->capacity) {
                        // Skip if this would exceed global capacity
                        continue;
                    }

                    // 2. Check Ticket Tier Availability
                    $ticket->refresh();
                    if ($ticket->quantity !== null && $ticket->available < $qtyNeeded) {
                        continue; // Skip if this specific tier is full
                    }

                    // 3. Promote
                    if ($entry['type'] === 'registration') {
                        $model->update([
                            'status' => 'confirmed',
                            'waitlist_position' => null, // Mark as promoted
                        ]);
                        
                        if ($model->user) {
                            $model->user->notify(new EventWaitlistPromoted($model));
                        } elseif ($model->guest_email) {
                            Notification::route('mail', $model->guest_email)
                                ->notify(new EventWaitlistPromoted($model));
                        }
                    } else {
                        // Paid Order Promotion
                        // Note: Promotion changes status to 'pending' to allow payment
                        $model->update([
                            'status' => 'pending',
                            'waitlist_position' => -1, // Mark as promoted (-1 is the special flag for "promoted but pending payment")
                        ]);

                        $model->registrations()->update([
                            'status' => 'pending',
                            'waitlist_position' => -1, // Mark as promoted
                        ]);

                        if ($model->user) {
                            $model->user->notify(new EventWaitlistPromoted($model));
                        } elseif ($model->guest_email) {
                            Notification::route('mail', $model->guest_email)
                                ->notify(new EventWaitlistPromoted($model));
                        }
                    }

                    // 4. Update ticket availability
                    if ($ticket->quantity !== null) {
                        $ticket->decrement('available', $qtyNeeded);
                    }

                    AuditLog::create([
                        'actor_id' => null, // System action
                        'action' => 'promoted_from_waitlist',
                        'model' => $entry['type'] === 'registration' ? EventRegistration::class : EventOrder::class,
                        'model_id' => $model->id,
                        'changes' => ['old_status' => 'waitlisted', 'new_status' => $model->status],
                    ]);

                    $promotedCount++;
                }

                return $promotedCount;
            });
        } catch (\Exception $e) {
            Log::error('Waitlist promotion failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get global confirmed count for an event (confirmed RSVPs + paid Orders)
     */
    public function getGlobalConfirmedCount(Event $event): int
    {
        // Count confirmed and attended registrations that ARE NOT linked to an order (RSVPs/Free)
        $registrations = EventRegistration::where('event_id', $event->id)
            ->whereNull('order_id')
            ->whereIn('status', ['confirmed', 'attended'])
            ->count();
        
        // Count paid and pending orders (Paid tickets)
        $orders = EventOrder::where('event_id', $event->id)
            ->whereIn('status', ['paid', 'pending'])
            ->sum('quantity');
            
        return (int) ($registrations + $orders);
    }
}
