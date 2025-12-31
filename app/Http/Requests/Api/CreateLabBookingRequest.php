<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @bodyParam lab_space_id integer required Must exist in lab_spaces
 * @bodyParam starts_at datetime required After now
 * @bodyParam ends_at datetime required After starts_at
 * @bodyParam purpose string required Min: 10, Max: 1000
 * @bodyParam title string optional Max: 255
 * @bodyParam slot_type string optional in:hourly,half_day,full_day
 */
class CreateLabBookingRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'lab_space_id' => ['required', 'exists:lab_spaces,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'purpose' => ['required', 'string', 'min:10', 'max:1000'],
            'title' => ['nullable', 'string', 'max:255'],
            'slot_type' => ['nullable', Rule::in(['hourly', 'half_day', 'full_day'])],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'lab_space_id' => ['description' => 'ID of the lab space to book', 'example' => 1],
            'starts_at' => ['description' => 'Booking start date and time', 'example' => '2025-01-15 09:00:00'],
            'ends_at' => ['description' => 'Booking end date and time', 'example' => '2025-01-15 13:00:00'],
            'purpose' => ['description' => 'Purpose of the booking (10-1000 chars)', 'example' => 'Working on a 3D printing project for community event decorations.'],
            'title' => ['description' => 'Optional booking title', 'example' => 'Community Event Prep'],
            'slot_type' => ['description' => 'Slot type: hourly, half_day, or full_day', 'example' => 'half_day'],
        ];
    }
}
