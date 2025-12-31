<?php

namespace Tests\Unit\Services\Events;

use App\DTOs\CreateEventDTO;
use App\DTOs\ListEventsFiltersDTO;
use App\DTOs\UpdateEventDTO;
use App\Exceptions\EventException;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Events\EventCapacityService;
use App\Services\Events\EventFeatureService;
use App\Services\Events\EventRegistrationService;
use App\Services\Events\EventService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * EventServiceTest
 *
 * Comprehensive tests for all Events domain services including
 * core event operations, registration, capacity, and featuring.
 *
 * Test Coverage:
 * - Event CRUD operations
 * - Event retrieval by ID and slug
 * - Event listing with filtering
 * - Event statistics
 * - Registration management
 * - Capacity management
 * - Featured event management
 */
class EventServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventService $eventService;
    private EventRegistrationService $registrationService;
    private EventCapacityService $capacityService;
    private EventFeatureService $featureService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventService = app(EventService::class);
        $this->registrationService = app(EventRegistrationService::class);
        $this->capacityService = app(EventCapacityService::class);
        $this->featureService = app(EventFeatureService::class);
    }

    // ============================================================
    // EVENT SERVICE TESTS
    // ============================================================

    #[Test]
    public function it_can_create_a_new_event(): void
    {
        // Arrange
        $organizer = User::factory()->create();
        $dto = new CreateEventDTO(
            title: 'Community Meetup',
            description: 'A great community event',
            category_id: 1,
            county_id: 1,
            starts_at: Carbon::now()->addDays(7)->toDateTime(),
            ends_at: Carbon::now()->addDays(7)->addHours(2)->toDateTime(),
            capacity: 50,
            venue: 'Nairobi Convention Center',
            image_path: 'events/community-meetup.jpg',
            tag_ids: [1, 2],
            status: 'published',
        );

        // Act
        $event = $this->eventService->create($organizer, $dto);

        // Assert
        $this->assertDatabaseHas('events', [
            'title' => 'Community Meetup',
            'county_id' => 1,
            'capacity' => 50,
        ]);
        $this->assertEquals('community-meetup', $event->slug);
        $this->assertEquals($organizer->id, $event->organizer_id);
    }

    #[Test]
    public function it_can_update_event_details(): void
    {
        // Arrange
        $organizer = User::factory()->create();
        $event = Event::factory()->create(['title' => 'Old Title']);
        $dto = new UpdateEventDTO(title: 'New Title', description: 'Updated description');

        // Act
        $updated = $this->eventService->update($organizer, $event, $dto);

        // Assert
        $this->assertEquals('New Title', $updated->title);
        $this->assertEquals('Updated description', $updated->description);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'New Title']);
    }

    #[Test]
    public function it_can_retrieve_event_by_id(): void
    {
        // Arrange
        $event = Event::factory()->create();

        // Act
        $retrieved = $this->eventService->getById($event->id);

        // Assert
        $this->assertEquals($event->id, $retrieved->id);
        $this->assertEquals($event->title, $retrieved->title);
    }

    #[Test]
    public function it_throws_exception_on_invalid_event_id(): void
    {
        // Act & Assert
        $this->expectException(EventException::class);
        $this->eventService->getById('invalid-id-99999');
    }

    #[Test]
    public function it_can_retrieve_event_by_slug(): void
    {
        // Arrange
        $event = Event::factory()->create(['slug' => 'test-event']);

        // Act
        $retrieved = $this->eventService->getBySlug('test-event');

        // Assert
        $this->assertEquals($event->id, $retrieved->id);
    }

    #[Test]
    public function it_can_list_events_with_filters(): void
    {
        // Arrange
        Event::factory()->count(5)->create(['county_id' => 1]);
        Event::factory()->count(3)->create(['county_id' => 2]);
        $filters = new ListEventsFiltersDTO(county_id: 1);

        // Act
        $results = $this->eventService->listEvents($filters);

        // Assert
        $this->assertGreaterThanOrEqual(5, $results->count());
    }

    #[Test]
    public function it_can_soft_delete_event(): void
    {
        // Arrange
        $organizer = User::factory()->create();
        $event = Event::factory()->create();

        // Act
        $this->eventService->delete($organizer, $event);

        // Assert
        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    #[Test]
    public function it_can_restore_event(): void
    {
        // Arrange
        $organizer = User::factory()->create();
        $event = Event::factory()->create();
        $event->delete();

        // Act
        $restored = $this->eventService->restore($organizer, $event);

        // Assert
        $this->assertNotSoftDeleted('events', ['id' => $event->id]);
        $this->assertNull($restored->deleted_at);
    }

    #[Test]
    public function it_can_get_event_statistics(): void
    {
        // Arrange
        $event = Event::factory()->create(['capacity' => 100]);
        $users = User::factory()->count(30)->create();
        foreach ($users as $user) {
            EventRegistration::create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'ticket_id' => 1,
                'confirmation_code' => 'TEST-' . $user->id,
                'status' => 'confirmed',
            ]);
        }

        // Act
        $stats = $this->eventService->getStatistics($event);

        // Assert
        $this->assertEquals($event->id, $stats['event_id']);
        $this->assertEquals(100, $stats['total_capacity']);
        $this->assertEquals(30, $stats['confirmed_registrations']);
        $this->assertEquals(70, $stats['available_capacity']);
        $this->assertEquals(30.0, $stats['utilization_percentage']);
    }

    // ============================================================
    // REGISTRATION SERVICE TESTS
    // ============================================================

    #[Test]
    public function it_can_register_user_for_event(): void
    {
        // Arrange
        $user = User::factory()->create();
        $event = Event::factory()->create(['capacity' => 50]);

        // Act
        $registration = $this->registrationService->registerUser($user, $event);

        // Assert
        $this->assertDatabaseHas('event_registrations', [
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
        ]);
        $this->assertEquals('confirmed', $registration->status);
    }

    #[Test]
    public function it_prevents_duplicate_registration(): void
    {
        // Arrange
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $this->registrationService->registerUser($user, $event);

        // Act & Assert
        $this->expectException(EventException::class);
        $this->registrationService->registerUser($user, $event);
    }

    #[Test]
    public function it_throws_exception_when_event_at_capacity(): void
    {
        // Arrange
        $event = Event::factory()->create(['capacity' => 2]);
        $users = User::factory()->count(3)->create();

        // Act & Assert
        $this->registrationService->registerUser($users[0], $event);
        $this->registrationService->registerUser($users[1], $event);

        $this->expectException(\App\Exceptions\EventCapacityExceededException::class);
        $this->registrationService->registerUser($users[2], $event);
    }

    #[Test]
    public function it_can_cancel_registration(): void
    {
        // Arrange
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $this->registrationService->registerUser($user, $event);

        // Act
        $result = $this->registrationService->cancelRegistration($user, $event, 'Change of plans');

        // Assert
        $this->assertTrue($result);
        $registration = EventRegistration::where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->first();
        $this->assertEquals('cancelled', $registration->status);
    }

    #[Test]
    public function it_can_check_if_user_is_registered(): void
    {
        // Arrange
        $user = User::factory()->create();
        $event = Event::factory()->create();

        // Act & Assert
        $this->assertFalse($this->registrationService->isRegistered($user, $event));

        $this->registrationService->registerUser($user, $event);
        $this->assertTrue($this->registrationService->isRegistered($user, $event));
    }

    #[Test]
    public function it_can_get_confirmed_registration_count(): void
    {
        // Arrange
        $event = Event::factory()->create();
        $users = User::factory()->count(10)->create();
        foreach ($users as $user) {
            $this->registrationService->registerUser($user, $event);
        }

        // Act
        $count = $this->registrationService->getConfirmedCount($event);

        // Assert
        $this->assertEquals(10, $count);
    }

    #[Test]
    public function it_can_bulk_register_users(): void
    {
        // Arrange
        $event = Event::factory()->create(['capacity' => 50]);
        $users = User::factory()->count(20)->create();
        $userIds = $users->pluck('id')->toArray();

        // Act
        $count = $this->registrationService->bulkRegister($event, $userIds);

        // Assert
        $this->assertEquals(20, $count);
        $this->assertEquals(20, $this->registrationService->getConfirmedCount($event));
    }

    #[Test]
    public function it_respects_bulk_registration_limit(): void
    {
        // Arrange
        $event = Event::factory()->create();
        $users = User::factory()->count(55)->create();
        $userIds = $users->pluck('id')->toArray();

        // Act & Assert
        $this->expectException(EventException::class);
        $this->registrationService->bulkRegister($event, $userIds);
    }

    #[Test]
    public function it_can_bulk_cancel_registrations(): void
    {
        // Arrange
        $event = Event::factory()->create(['capacity' => 50]);
        $users = User::factory()->count(20)->create();
        foreach ($users as $user) {
            $this->registrationService->registerUser($user, $event);
        }
        $userIds = $users->pluck('id')->toArray();

        // Act
        $count = $this->registrationService->bulkCancel($event, $userIds);

        // Assert
        $this->assertEquals(20, $count);
    }

    // ============================================================
    // CAPACITY SERVICE TESTS
    // ============================================================

    #[Test]
    public function it_can_check_event_has_capacity(): void
    {
        // Arrange
        $event = Event::factory()->create(['capacity' => 50]);

        // Act & Assert
        $this->assertTrue($this->capacityService->hasCapacity($event));
        $this->assertTrue($this->capacityService->hasCapacity($event, 50));
        $this->assertFalse($this->capacityService->hasCapacity($event, 51));
    }

    #[Test]
    public function it_can_get_available_capacity(): void
    {
        // Arrange
        $event = Event::factory()->create(['capacity' => 100]);
        $users = User::factory()->count(30)->create();
        foreach ($users as $user) {
            EventRegistration::create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'ticket_id' => 1,
                'confirmation_code' => 'TEST-' . $user->id,
                'status' => 'confirmed',
            ]);
        }

        // Act
        $available = $this->capacityService->getAvailableCapacity($event);

        // Assert
        $this->assertEquals(70, $available);
    }

    #[Test]
    public function it_can_get_attendee_count(): void
    {
        // Arrange
        $event = Event::factory()->create(['capacity' => 100]);
        $users = User::factory()->count(25)->create();
        foreach ($users as $user) {
            EventRegistration::create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'ticket_id' => 1,
                'confirmation_code' => 'TEST-' . $user->id,
                'status' => 'confirmed',
            ]);
        }

        // Act
        $count = $this->capacityService->getAttendeeCount($event);

        // Assert
        $this->assertEquals(25, $count);
    }

    #[Test]
    public function it_can_update_event_capacity(): void
    {
        // Arrange
        $organizer = User::factory()->create();
        $event = Event::factory()->create(['capacity' => 50]);

        // Act
        $updated = $this->capacityService->updateCapacity($organizer, $event, 100);

        // Assert
        $this->assertEquals(100, $updated->capacity);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'capacity' => 100]);
    }

    #[Test]
    public function it_throws_exception_when_reducing_below_attendees(): void
    {
        // Arrange
        $organizer = User::factory()->create();
        $event = Event::factory()->create(['capacity' => 50]);
        $users = User::factory()->count(30)->create();
        foreach ($users as $user) {
            EventRegistration::create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'ticket_id' => 1,
                'confirmation_code' => 'TEST-' . $user->id,
                'status' => 'confirmed',
            ]);
        }

        // Act & Assert
        $this->expectException(EventException::class);
        $this->capacityService->updateCapacity($organizer, $event, 20);
    }

    #[Test]
    public function it_can_check_if_at_capacity(): void
    {
        // Arrange
        $event = Event::factory()->create(['capacity' => 5]);
        $users = User::factory()->count(5)->create();
        foreach ($users as $user) {
            EventRegistration::create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'ticket_id' => 1,
                'confirmation_code' => 'TEST-' . $user->id,
                'status' => 'confirmed',
            ]);
        }

        // Act & Assert
        $this->assertTrue($this->capacityService->isAtCapacity($event));
    }

    #[Test]
    public function it_can_calculate_utilization(): void
    {
        // Arrange
        $event = Event::factory()->create(['capacity' => 100]);
        $users = User::factory()->count(50)->create();
        foreach ($users as $user) {
            EventRegistration::create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'ticket_id' => 1,
                'confirmation_code' => 'TEST-' . $user->id,
                'status' => 'confirmed',
            ]);
        }

        // Act
        $utilization = $this->capacityService->getUtilization($event);

        // Assert
        $this->assertEquals(50.0, $utilization);
    }

    // ============================================================
    // FEATURE SERVICE TESTS
    // ============================================================

    #[Test]
    public function it_can_feature_an_event(): void
    {
        // Arrange
        $admin = User::factory()->create();
        $event = Event::factory()->create();

        // Act
        $this->featureService->featureEvent($admin, $event, 5, 'Popular event');

        // Assert
        $this->assertTrue($this->featureService->isFeatured($event));
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'featured' => true,
        ]);
    }

    #[Test]
    public function it_can_unfeature_an_event(): void
    {
        // Arrange
        $admin = User::factory()->create();
        $event = Event::factory()->create();
        $this->featureService->featureEvent($admin, $event);

        // Act
        $this->featureService->unfeatureEvent($admin, $event);

        // Assert
        $this->assertFalse($this->featureService->isFeatured($event));
    }

    #[Test]
    public function it_can_check_if_event_is_featured(): void
    {
        // Arrange
        $admin = User::factory()->create();
        $event = Event::factory()->create();

        // Act & Assert
        $this->assertFalse($this->featureService->isFeatured($event));

        $this->featureService->featureEvent($admin, $event);
        $this->assertTrue($this->featureService->isFeatured($event));
    }


    #[Test]
    public function it_can_bulk_feature_events(): void
    {
        // Arrange
        $admin = User::factory()->create();
        $events = Event::factory()->count(10)->create();
        $eventIds = $events->pluck('id')->toArray();

        // Act
        $count = $this->featureService->bulkFeature($admin, $eventIds);

        // Assert
        $this->assertEquals(10, $count);
        $this->assertEquals(10, Event::where('featured', true)->count());
    }

    #[Test]
    public function it_respects_bulk_feature_limit(): void
    {
        // Arrange
        $admin = User::factory()->create();
        $events = Event::factory()->count(25)->create();
        $eventIds = $events->pluck('id')->toArray();

        // Act & Assert
        $this->expectException(EventException::class);
        $this->featureService->bulkFeature($admin, $eventIds);
    }

    #[Test]
    public function it_can_get_featured_events_by_county(): void
    {
        // Arrange
        $admin = User::factory()->create();
        Event::factory()->count(5)->create(['county_id' => 1]);
        Event::factory()->count(3)->create(['county_id' => 2]);

        $nairobiEvents = Event::where('county_id', 1)->get();
        foreach ($nairobiEvents as $event) {
            $this->featureService->featureEvent($admin, $event);
        }

        // Act
        $featured = $this->featureService->getFeaturedEvents(1);

        // Assert
        $this->assertGreaterThanOrEqual(5, $featured->count());
    }
}
