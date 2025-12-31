<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Feature Event Request
 *
 * Validates the featured status update for an event.
 *
 * @group Admin Events
 */
class FeatureEventRequest extends FormRequest
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
            'until' => 'nullable|date|after:now',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'until' => 'featured until date',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'until.after' => 'Featured until date must be in the future',
            'until.date' => 'Featured until date must be a valid date',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'until' => ['description' => 'Date until the event should be featured', 'example' => '2025-06-01 00:00:00'],
        ];
    }
}
