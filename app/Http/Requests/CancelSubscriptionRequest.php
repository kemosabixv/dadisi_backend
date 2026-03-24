<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
            'immediate' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the body parameters for the documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'reason' => [
                'description' => 'Reason for cancelling the subscription',
                'example' => 'No longer using the service.',
            ],
            'immediate' => [
                'description' => 'Whether to cancel immediately or at the end of the current period',
                'example' => false,
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'reason.string' => 'Cancellation reason must be a string.',
            'reason.max' => 'Cancellation reason cannot exceed 500 characters.',
            'immediate.boolean' => 'The immediate field must be a boolean value.',
        ];
    }
}
