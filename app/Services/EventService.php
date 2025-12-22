<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Models\Registration;
use App\Models\Ticket;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class EventService
{
    protected $quotaService;
    protected $qrCodeService;

    public function __construct(EventQuotaService $quotaService, QrCodeService $qrCodeService)
    {
        $this->quotaService = $quotaService;
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Create a new event with quota check.
     */
    public function createEvent(User $user, array $data): Event
    {
        if (!$this->quotaService->canCreateEvent($user)) {
            throw new \Exception("Monthly event creation quota exceeded for your plan.");
        }

        $data['created_by'] = $user->id;
        $data['organizer_id'] = $data['organizer_id'] ?? $user->id;
        $data['slug'] = $data['slug'] ?? Str::slug($data['title'] . '-' . uniqid());

        return Event::create($data);
    }

    /**
     * Handle event image upload.
     */
    public function uploadImage(Event $event, UploadedFile $file): string
    {
        if ($event->image_path) {
            Storage::disk('public')->delete($event->image_path);
        }

        $path = $file->store('events/banners', 'public');
        $event->update(['image_path' => $path]);

        return $path;
    }

    /**
     * Register a user for an event.
     */
    public function registerUser(Event $event, User $user, Ticket $ticket, array $additionalData = []): Registration
    {
        // 1. Check if registration is open
        if (!$event->isRegistrationOpen()) {
            throw new \Exception("Registration for this event is closed.");
        }

        // 2. Check participation quota
        if (!$this->quotaService->canParticipate($user)) {
            throw new \Exception("Monthly event participation quota exceeded for your plan.");
        }

        // 3. Check if user already registered
        $existing = Registration::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'attended', 'pending'])
            ->first();
            
        if ($existing) {
            throw new \Exception("You are already registered for this event.");
        }

        // 4. Handle capacity and waitlist
        $status = 'confirmed';
        $waitlistPosition = null;

        if (!$event->hasCapacity()) {
            if ($event->waitlist_enabled) {
                $status = 'waitlisted';
                $waitlistPosition = $this->getNextWaitlistPosition($event);
            } else {
                throw new \Exception("This event is at full capacity.");
            }
        }

        // 5. Create registration
        $registration = Registration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'confirmation_code' => $this->generateConfirmationCode(),
            'status' => $status,
            'waitlist_position' => $waitlistPosition,
            'qr_code_token' => $this->qrCodeService->generateQrToken(),
        ]);

        // 6. Generate QR code image
        if ($status === 'confirmed') {
            $this->qrCodeService->generateQrCode($registration);
        }

        return $registration;
    }

    /**
     * Generate a unique confirmation code.
     */
    protected function generateConfirmationCode(): string
    {
        return 'REG-' . strtoupper(Str::random(10));
    }

    /**
     * Get next waitlist position for an event.
     */
    protected function getNextWaitlistPosition(Event $event): int
    {
        return Registration::where('event_id', $event->id)
            ->where('status', 'waitlisted')
            ->count() + 1;
    }

    /**
     * Promote users from waitlist when capacity opens up.
     */
    public function processWaitlist(Event $event): int
    {
        $available = $event->getRemainingCapacity();
        if ($available <= 0) return 0;

        $waitlisted = Registration::where('event_id', $event->id)
            ->where('status', 'waitlisted')
            ->orderBy('waitlist_position', 'asc')
            ->limit($available)
            ->get();

        foreach ($waitlisted as $reg) {
            $reg->update([
                'status' => 'confirmed',
                'waitlist_position' => null
            ]);
            // TODO: Send notification to user
        }

        return $waitlisted->count();
    }
}
