<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store Event Request
 *
 * Validates all fields required to create a new event, including event details,
 * tickets, speakers, and tags.
 *
 * @group Admin Events
 */
class StoreEventRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|integer|exists:event_categories,id',
            'county_id' => 'required|integer|exists:counties,id',
            'status' => 'nullable|string|in:draft,published,cancelled,suspended',
            
            // Timing
            'starts_at' => 'required|date|after:now',
            'ends_at' => 'required|date|after:starts_at',
            'registration_deadline' => 'nullable|date|before:starts_at',
            
            // Location
            'venue' => 'nullable|string|max:255|required_if:is_online,false',
            'is_online' => 'required|boolean',
            'online_link' => 'nullable|url|required_if:is_online,true',
            
            // Capacity & Waitlist
            'capacity' => 'nullable|integer|min:1',
            'waitlist_enabled' => 'nullable|boolean',
            'waitlist_capacity' => 'nullable|integer|min:1|required_if:waitlist_enabled,true',
            
            // Image & Featured
            'image_path' => 'nullable|string|max:500',
            'featured' => 'nullable|boolean',
            'featured_until' => 'nullable|date|after:now',
            
            // Pricing
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|in:KES,USD',
            
            // Tags
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:event_tags,id',
            
            // Tickets
            'tickets' => 'nullable|array|min:1',
            'tickets.*.name' => 'required_with:tickets|string|max:255',
            'tickets.*.description' => 'nullable|string',
            'tickets.*.price' => 'required_with:tickets|numeric|min:0',
            'tickets.*.quantity' => 'required_with:tickets|integer|min:1',
            'tickets.*.is_active' => 'nullable|boolean',
            
            // Speakers
            'speakers' => 'nullable|array',
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
            'starts_at.after' => 'Event must start in the future',
            'ends_at.after' => 'Event must end after start time',
            'registration_deadline.before' => 'Registration deadline must be before event start time',
            'online_link.required_if' => 'Online link is required for online events',
            'waitlist_capacity.required_if' => 'Waitlist capacity is required when waitlist is enabled',
            'title.required' => 'Event title is required',
            'description.required' => 'Event description is required',
            'category_id.required' => 'Event category is required',
            'county_id.required' => 'Event county is required',
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
            'title' => ['description' => 'Event title', 'example' => 'Tech Conference 2025'],
            'description' => ['description' => 'Full event description', 'example' => 'Join us for a day of tech talks and networking.'],
            'category_id' => ['description' => 'Event category ID', 'example' => 1],
            'county_id' => ['description' => 'Event county ID', 'example' => 35],
            'status' => ['description' => 'Event status', 'example' => 'draft'],
            'starts_at' => ['description' => 'Event start date and time', 'example' => '2025-06-15 09:00:00'],
            'ends_at' => ['description' => 'Event end date and time', 'example' => '2025-06-15 17:00:00'],
            'registration_deadline' => ['description' => 'Registration deadline', 'example' => '2025-06-10 23:59:59'],
            'venue' => ['description' => 'Physical venue (required if not online)', 'example' => 'Nairobi Convention Centre'],
            'is_online' => ['description' => 'Whether this is an online event', 'example' => false],
            'online_link' => ['description' => 'Online meeting link (required if online)', 'example' => 'https://zoom.us/j/123456789'],
            'capacity' => ['description' => 'Maximum number of attendees', 'example' => 100],
            'waitlist_enabled' => ['description' => 'Enable waitlist for sold-out events', 'example' => true],
            'waitlist_capacity' => ['description' => 'Maximum waitlist size', 'example' => 20],
            'image_path' => ['description' => 'Path to event image', 'example' => 'events/tech-conf-2025.jpg'],
            'featured' => ['description' => 'Whether event is featured', 'example' => true],
            'featured_until' => ['description' => 'Featured until date', 'example' => '2025-06-01 00:00:00'],
            'price' => ['description' => 'Base event price', 'example' => 500.00],
            'currency' => ['description' => 'Price currency', 'example' => 'KES'],
            'tag_ids' => ['description' => 'Array of event tag IDs', 'example' => [1, 2]],
            'tickets' => ['description' => 'Array of ticket types', 'example' => [['name' => 'General Admission', 'price' => 500, 'quantity' => 50]]],
            'speakers' => ['description' => 'Array of speakers', 'example' => [['name' => 'John Doe', 'designation' => 'CTO', 'company' => 'Tech Corp']]],
        ];
    }
}
