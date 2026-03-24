<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ListMemberProfilesRequest extends FormRequest
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
            'county_id' => ['nullable', 'integer', 'exists:counties,id'],
            'membership_type' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get the query parameters for the documentation.
     */
    public function queryParameters(): array
    {
        return [
            'county_id' => [
                'description' => 'Filter by county ID',
                'example' => 1,
            ],
            'membership_type' => [
                'description' => 'Filter by membership type (e.g., individual, corporate)',
                'example' => 'individual',
            ],
            'search' => [
                'description' => 'Search by name, email, or occupation',
                'example' => 'John',
            ],
            'page' => [
                'description' => 'Page number for pagination',
                'example' => 1,
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'county_id.exists' => 'The specified county does not exist.',
            'search.max' => 'Search term cannot exceed 255 characters.',
            'page.min' => 'Page number must be at least 1.',
        ];
    }
}
