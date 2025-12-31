<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * List Events Request
 *
 * Validates query parameters for listing events with filtering and sorting.
 *
 * @group Admin Events
 */
class ListEventsRequest extends FormRequest
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
            'status' => 'nullable|string|in:draft,pending_approval,published,rejected,cancelled,suspended,all',
            'event_type' => 'nullable|string|in:organization,user',
            'featured' => 'nullable|boolean',
            'organizer_id' => 'nullable|integer|exists:users,id',
            'search' => 'nullable|string|max:255',
            'sort_by' => 'nullable|string|in:title,starts_at,status,created_at',
            'sort_dir' => 'nullable|string|in:asc,desc',
            'upcoming' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'status' => 'event status',
            'event_type' => 'event type',
            'featured' => 'featured status',
            'organizer_id' => 'organizer',
            'search' => 'search query',
            'sort_by' => 'sort field',
            'sort_dir' => 'sort direction',
            'per_page' => 'items per page',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Invalid event status. Allowed: draft, pending_approval, published, rejected, cancelled, suspended, all',
            'event_type.in' => 'Invalid event type. Allowed: organization, user',
            'sort_by.in' => 'Invalid sort field. Allowed: title, starts_at, status, created_at',
            'sort_dir.in' => 'Invalid sort direction. Allowed: asc, desc',
            'per_page.max' => 'Maximum 100 items per page allowed',
        ];
    }

    public function queryParameters(): array
    {
        return [
            'status' => ['description' => 'Filter by status: draft, pending_approval, published, etc.', 'example' => 'published'],
            'event_type' => ['description' => 'Filter by event type: organization or user', 'example' => 'organization'],
            'featured' => ['description' => 'Filter by featured status', 'example' => true],
            'organizer_id' => ['description' => 'Filter by organizer user ID', 'example' => 1],
            'search' => ['description' => 'Search events by title', 'example' => 'Tech Conference'],
            'sort_by' => ['description' => 'Sort field: title, starts_at, status, created_at', 'example' => 'starts_at'],
            'sort_dir' => ['description' => 'Sort direction: asc or desc', 'example' => 'asc'],
            'upcoming' => ['description' => 'Filter for upcoming events only', 'example' => true],
            'per_page' => ['description' => 'Items per page (max 100)', 'example' => 15],
        ];
    }
}
