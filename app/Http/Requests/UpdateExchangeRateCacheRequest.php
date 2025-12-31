<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for updating exchange rate cache settings
 *
 * Validates the cache duration selection when configuring how long
 * exchange rates should be cached locally before requiring a refresh.
 */
class UpdateExchangeRateCacheRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cache_minutes' => 'required|integer|in:4320,7200,10080', // 3, 5, 7 days
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'cache_minutes' => [
                'description' => 'Cache duration in minutes (4320=3 days, 7200=5 days, 10080=7 days)',
                'example' => 4320,
            ],
        ];
    }

    /**
     * Get custom validation messages
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cache_minutes.required' => 'Cache duration is required',
            'cache_minutes.integer' => 'Cache duration must be a whole number',
            'cache_minutes.in' => 'Invalid cache duration. Must be one of: 4320 (3 days), 7200 (5 days), 10080 (7 days)',
        ];
    }

    /**
     * Get custom attribute names for validation errors
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cache_minutes' => 'cache duration',
        ];
    }
}
