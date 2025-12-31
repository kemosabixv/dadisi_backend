<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Event Request
 *
 * Validates fields for updating an existing event. All fields are optional
 * to allow partial updates.
 *
 * @group Admin Events
 */
class UpdateEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Event core details
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:categories,id',
            'county_id' => 'nullable|integer|exists:counties,id',
            
            // Timing
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'registration_deadline' => 'nullable|date|before:starts_at',
            
            // Location
            'venue' => 'nullable|string|max:255',
            'is_online' => 'nullable|boolean',
            'online_link' => 'nullable|url',
            
            // Capacity & Waitlist
            'capacity' => 'nullable|integer|min:1',
            'waitlist_enabled' => 'nullable|boolean',
            'waitlist_capacity' => 'nullable|integer|min:1',
            
            // Image & Featured
            'image_path' => 'nullable|string|max:500',
            'featured' => 'nullable|boolean',
            'featured_until' => 'nullable|date|after:now',
            
            // Pricing
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|in:KES,USD',
            
            // Status
            'status' => 'nullable|string|in:draft,published,cancelled,suspended',
            
            // Tags
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:event_tags,id',
            
            // Tickets
            'tickets' => 'nullable|array',
            'tickets.*.id' => 'nullable|integer',
            'tickets.*.name' => 'required_with:tickets|string|max:255',
            'tickets.*.description' => 'nullable|string',
            'tickets.*.price' => 'required_with:tickets|numeric|min:0',
            'tickets.*.quantity' => 'required_with:tickets|integer|min:1',
            'tickets.*.is_active' => 'nullable|boolean',
            
            // Speakers
            'speakers' => 'nullable|array',
            'speakers.*.id' => 'nullable|integer',
            'speakers.*.name' => 'required_with:speakers|string|max:255',
            'speakers.*.designation' => 'nullable|string|max:255',
            'speakers.*.company' => 'nullable|string|max:255',
            'speakers.*.bio' => 'nullable|string',
            'speakers.*.is_featured' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'title' => 'event title',
            'description' => 'event description',
            'category_id' => 'event category',
            'county_id' => 'event county',
            'starts_at' => 'event start time',
            'ends_at' => 'event end time',
            'registration_deadline' => 'registration deadline',
            'venue' => 'event venue',
            'is_online' => 'event type (online/in-person)',
            'online_link' => 'online event link',
            'capacity' => 'event capacity',
            'waitlist_enabled' => 'waitlist enabled',
            'waitlist_capacity' => 'waitlist capacity',
            'image_path' => 'event image',
            'featured' => 'featured status',
            'featured_until' => 'featured until date',
            'price' => 'event price',
            'currency' => 'currency',
            'status' => 'event status',
            'tag_ids' => 'event tags',
            'tickets' => 'event tickets',
            'speakers' => 'event speakers',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'ends_at.after' => 'Event must end after start time',
            'registration_deadline.before' => 'Registration deadline must be before event start time',
            'status.in' => 'Invalid event status. Allowed: draft, published, cancelled, suspended',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure booleans are properly cast
        if ($this->has('is_online')) {
            $this->merge(['is_online' => filter_var($this->is_online, FILTER_VALIDATE_BOOLEAN)]);
        }
        if ($this->has('featured')) {
            $this->merge(['featured' => filter_var($this->featured, FILTER_VALIDATE_BOOLEAN)]);
        }
        if ($this->has('waitlist_enabled')) {
            $this->merge(['waitlist_enabled' => filter_var($this->waitlist_enabled, FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Event title', 'example' => 'Updated Tech Conference 2025'],
            'description' => ['description' => 'Full event description', 'example' => 'Updated event description.'],
            'category_id' => ['description' => 'Event category ID', 'example' => 1],
            'county_id' => ['description' => 'Event county ID', 'example' => 35],
            'status' => ['description' => 'Event status', 'example' => 'published'],
            'starts_at' => ['description' => 'Event start date and time', 'example' => '2025-06-16 09:00:00'],
            'ends_at' => ['description' => 'Event end date and time', 'example' => '2025-06-16 17:00:00'],
            'registration_deadline' => ['description' => 'Registration deadline', 'example' => '2025-06-11 23:59:59'],
            'venue' => ['description' => 'Physical venue', 'example' => 'Updated Venue Location'],
            'is_online' => ['description' => 'Whether this is an online event', 'example' => false],
            'online_link' => ['description' => 'Online meeting link', 'example' => null],
            'capacity' => ['description' => 'Maximum number of attendees', 'example' => 150],
            'waitlist_enabled' => ['description' => 'Enable waitlist for sold-out events', 'example' => true],
            'waitlist_capacity' => ['description' => 'Maximum waitlist size', 'example' => 25],
            'image_path' => ['description' => 'Path to event image', 'example' => 'events/updated-image.jpg'],
            'featured' => ['description' => 'Whether event is featured', 'example' => false],
            'featured_until' => ['description' => 'Featured until date', 'example' => null],
            'price' => ['description' => 'Base event price', 'example' => 750.00],
            'currency' => ['description' => 'Price currency', 'example' => 'KES'],
            'tag_ids' => ['description' => 'Array of event tag IDs', 'example' => [1, 3]],
            'tickets' => ['description' => 'Array of ticket types', 'example' => [['id' => 1, 'name' => 'VIP', 'price' => 1000, 'quantity' => 20]]],
            'speakers' => ['description' => 'Array of speakers', 'example' => [['id' => 1, 'name' => 'Jane Doe', 'designation' => 'CEO']]],
        ];
    }
}
