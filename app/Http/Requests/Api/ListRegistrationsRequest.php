<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * List Registrations Request
 *
 * Validates query parameters for listing event registrations with filtering.
 *
 * @group Admin Events
 */
class ListRegistrationsRequest extends FormRequest
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
            'status' => 'nullable|string|in:pending,confirmed,cancelled,waitlisted',
            'waitlist' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'status' => 'registration status',
            'waitlist' => 'waitlist filter',
            'per_page' => 'items per page',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Invalid registration status. Allowed: pending, confirmed, cancelled, waitlisted',
            'per_page.max' => 'Maximum 100 items per page allowed',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('waitlist')) {
            $this->merge(['waitlist' => filter_var($this->waitlist, FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    public function queryParameters(): array
    {
        return [
            'status' => ['description' => 'Filter by status: pending, confirmed, cancelled, waitlisted', 'example' => 'confirmed'],
            'waitlist' => ['description' => 'Filter for waitlisted registrations only', 'example' => false],
            'per_page' => ['description' => 'Items per page (max 100)', 'example' => 15],
        ];
    }
}
