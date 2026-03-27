<?php

namespace Tests\Feature\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\EventRegistrationCancelled;
use App\Services\Contracts\EventRegistrationServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EventRegistrationCancellationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected EventRegistrationServiceContract $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EventRegistrationServiceContract::class);
    }

    public function test_it_sends_notification_to_registered_user_on_cancellation()
    {
        Notification::fake();

        $user = User::factory()->create();
        $event = Event::factory()->create(['starts_at' => now()->addDays(10)]);
        $ticket = Ticket::factory()->create(['event_id' => $event->id]);
        
        $registration = EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'confirmation_code' => 'TEST1234',
            'status' => 'confirmed'
        ]);

        $this->service->cancelRegistration($user, $event);

        Notification::assertSentTo(
            $user,
            EventRegistrationCancelled::class,
            function ($notification, $channels) use ($user, $registration) {
                return $notification->via($user) === ['mail', 'database', \App\Channels\SupabaseChannel::class];
            }
        );
    }

    public function test_it_sends_notification_to_guest_on_cancellation()
    {
        Notification::fake();

        $event = Event::factory()->create(['starts_at' => now()->addDays(10)]);
        $ticket = Ticket::factory()->create(['event_id' => $event->id]);
        $guestEmail = 'guest@example.com';
        
        $registration = EventRegistration::create([
            'event_id' => $event->id,
            'guest_email' => $guestEmail,
            'ticket_id' => $ticket->id,
            'confirmation_code' => 'GUEST123',
            'status' => 'confirmed'
        ]);

        $this->service->cancelRegistration(null, $event, 'Guest request', $registration);

        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            EventRegistrationCancelled::class,
            function ($notification, $channels, $notifiable) use ($guestEmail) {
                return $notifiable->routes['mail'] === $guestEmail;
            }
        );
    }
}
