<?php

namespace Tests\Feature\Api\Events;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\County;
use App\Models\EventTag;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminEventControllerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $shouldSeedRoles = true;

    protected User $admin;
    protected EventCategory $category;
    protected County $county;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()
            ->create(['email' => 'admin@test.local']);

        // Assign admin role (required by AdminMiddleware)
        $this->admin->assignRole('admin');

        // Grant event management permissions
        $this->admin->givePermissionTo([
            'create_events',
            'edit_events',
            'delete_events',
            'view_all_events',
            'manage_event_attendees'
        ]);

        // Create test data - using EventCategory factory for event categories
        $this->category = EventCategory::factory()->create();
        $this->county = County::factory()->create();
    }

    /**
     * Test: Create Event with Valid Data
     * Expects: 201 Created with Event resource
     */
    public function test_store_event_with_valid_data(): void
    {
        $eventData = [
            'title' => 'Tech Summit 2026',
            'description' => 'Annual technology conference',
            'category_id' => $this->category->id,
            'county_id' => $this->county->id,
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'ends_at' => now()->addMonth()->addHours(8)->toDateTimeString(),
            'venue' => 'Tech Hub Arena',
            'is_online' => false,
            'capacity' => 500,
            'waitlist_enabled' => true,
            'waitlist_capacity' => 100,
            'price' => 2500,
            'currency' => 'KES',
            'status' => 'draft',
            'featured' => false,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/events', $eventData);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'description',
                    'status',
                    'starts_at',
                    'ends_at',
                    'venue',
                    'capacity',
                    'category' => ['id', 'name'],
                    'county' => ['id', 'name'],
                ]
            ]);

        $this->assertDatabaseHas('events', [
            'title' => 'Tech Summit 2026',
            'status' => 'draft',
        ]);
    }

    /**
     * Test: Create Event with Missing Required Field
     * Expects: 422 Validation Error
     */
    public function test_store_event_fails_without_title(): void
    {
        $eventData = [
            'description' => 'Missing title',
            'category_id' => $this->category->id,
            'starts_at' => now()->addMonth()->toDateTimeString(),
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/events', $eventData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    /**
     * Test: Create Online Event with Missing Link
     * Expects: 422 Validation Error
     */
    public function test_store_online_event_requires_link(): void
    {
        $eventData = [
            'title' => 'Online Event',
            'description' => 'An online event',
            'category_id' => $this->category->id,
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'is_online' => true,
            // Missing online_link
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/events', $eventData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['online_link']);
    }

    /**
     * Test: Create In-Person Event with Missing Venue
     * Expects: 422 Validation Error
     */
    public function test_store_inperson_event_requires_venue(): void
    {
        $eventData = [
            'title' => 'In-Person Event',
            'description' => 'An in-person event',
            'category_id' => $this->category->id,
            'county_id' => $this->county->id,
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'ends_at' => now()->addMonth()->addHours(8)->toDateTimeString(),
            'is_online' => false,
            // Missing venue
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/events', $eventData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['venue']);
    }

    /**
     * Test: Create Event with Nested Tickets
     * Expects: 201 Created with tickets in response
     */
    public function test_store_event_with_tickets(): void
    {
        $eventData = [
            'title' => 'Paid Event',
            'description' => 'Event with tickets',
            'category_id' => $this->category->id,
            'county_id' => $this->county->id,
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'ends_at' => now()->addMonth()->addHours(8)->toDateTimeString(),
            'venue' => 'Main Hall',
            'is_online' => false,
            'price' => 1000,
            'currency' => 'KES',
            'status' => 'draft',
            'tickets' => [
                [
                    'name' => 'Early Bird',
                    'price' => 800,
                    'quantity' => 50,
                ],
                [
                    'name' => 'Regular',
                    'price' => 1000,
                    'quantity' => 100,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/events', $eventData);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.tickets');
    }

    /**
     * Test: Create Event with Nested Speakers
     * Expects: 201 Created with speakers in response
     */
    public function test_store_event_with_speakers(): void
    {
        $eventData = [
            'title' => 'Speaker Event',
            'description' => 'Event with speakers',
            'category_id' => $this->category->id,
            'county_id' => $this->county->id,
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'ends_at' => now()->addMonth()->addHours(8)->toDateTimeString(),
            'venue' => 'Auditorium',
            'is_online' => false,
            'status' => 'draft',
            'speakers' => [
                [
                    'name' => 'John Doe',
                    'designation' => 'Chief Technology Officer',
                    'company' => 'Tech Corp',
                    'bio' => 'Expert in cloud technologies',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/events', $eventData);

        $response->assertCreated()
            ->assertJsonCount(1, 'data.speakers');
    }

    /**
     * Test: Update Event with Valid Data
     * Expects: 200 OK with updated Event resource
     */
    public function test_update_event_with_valid_data(): void
    {
        $event = Event::factory()
            ->create([
                'title' => 'Original Title',
                'created_by' => $this->admin->id,
                'category_id' => $this->category->id,
                'county_id' => $this->county->id,
            ]);

        $updateData = [
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/events/{$event->id}", $updateData);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Updated Title',
        ]);
    }

    /**
     * Test: Delete Event
     * Expects: 204 No Content
     */
    public function test_delete_event(): void
    {
        $event = Event::factory()
            ->create([
                'created_by' => $this->admin->id,
                'category_id' => $this->category->id,
                'county_id' => $this->county->id,
            ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/events/{$event->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    /**
     * Test: Publish Event
     * Expects: 200 OK with updated status
     */
    public function test_publish_event(): void
    {
        $event = Event::factory()
            ->create([
                'status' => 'draft',
                'created_by' => $this->admin->id,
                'category_id' => $this->category->id,
                'county_id' => $this->county->id,
            ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/events/{$event->id}/publish");

        $response->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    /**
     * Test: Feature Event
     * Expects: 200 OK with featured set to true
     */
    public function test_feature_event(): void
    {
        $event = Event::factory()
            ->create([
                'featured' => false,
                'created_by' => $this->admin->id,
                'category_id' => $this->category->id,
                'county_id' => $this->county->id,
            ]);

        $featureData = [
            'featured_until' => now()->addMonth()->toDateString(),
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/events/{$event->id}/feature", $featureData);

        $response->assertOk()
            ->assertJsonPath('data.featured', true);
    }

    /**
     * Test: List Events with Pagination
     * Expects: 200 OK with paginated events
     */
    public function test_list_events_with_pagination(): void
    {
        Event::factory()
            ->count(25)
            ->create([
                'category_id' => $this->category->id,
                'county_id' => $this->county->id,
            ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/events?per_page=15');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 15);
    }

    /**
     * Test: List Events with Filter by Status
     * Expects: 200 OK with filtered events
     */
    public function test_list_events_filter_by_status(): void
    {
        Event::factory()
            ->count(5)
            ->create([
                'status' => 'published',
                'category_id' => $this->category->id,
                'county_id' => $this->county->id,
            ]);

        Event::factory()
            ->count(5)
            ->create([
                'status' => 'draft',
                'category_id' => $this->category->id,
                'county_id' => $this->county->id,
            ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/events?status=published');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 5);
    }

    /**
     * Test: List Events with Search
     * Expects: 200 OK with matching events
     */
    public function test_list_events_search(): void
    {
        Event::factory()
            ->create([
                'title' => 'Tech Summit',
                'category_id' => $this->category->id,
                'county_id' => $this->county->id,
            ]);

        Event::factory()
            ->create([
                'title' => 'Business Conference',
                'category_id' => $this->category->id,
                'county_id' => $this->county->id,
            ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/events?search=Tech');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Tech Summit');
    }

    /**
     * Test: List Registrations for Event
     * Expects: 200 OK with registrations
     */
    public function test_list_event_registrations(): void
    {
        $event = Event::factory()
            ->create([
                'created_by' => $this->admin->id,
                'category_id' => $this->category->id,
                'county_id' => $this->county->id,
            ]);

        $user = User::factory()->create();
        $ticket = $event->tickets()->create([
            'name' => 'General Admission',
            'quantity' => 100,
            'price' => 1000,
        ]);

        $event->registrations()->create([
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'status' => 'confirmed',
            'confirmation_code' => 'ABC123',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/events/{$event->id}/registrations");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'confirmed');
    }

    /**
     * Test: Unauthorized Access (No Permission)
     * Expects: 403 Forbidden
     */
    public function test_create_event_without_permission(): void
    {
        $user = User::factory()->create();

        $eventData = [
            'title' => 'Unauthorized Event',
            'description' => 'Should fail',
            'category_id' => $this->category->id,
            'starts_at' => now()->addMonth()->toDateTimeString(),
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/events', $eventData);

        $response->assertForbidden();
    }

    /**
     * Test: Unauthenticated Access
     * Expects: 401 Unauthorized
     */
    public function test_create_event_unauthenticated(): void
    {
        $eventData = [
            'title' => 'Unauthorized Event',
            'description' => 'Should fail',
            'category_id' => $this->category->id,
            'starts_at' => now()->addMonth()->toDateTimeString(),
        ];

        $response = $this->postJson('/api/admin/events', $eventData);

        $response->assertUnauthorized();
    }
}
