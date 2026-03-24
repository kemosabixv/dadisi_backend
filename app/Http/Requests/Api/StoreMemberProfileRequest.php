<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authenticated users can create their own profile
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'county_id' => ['required', 'integer', Rule::exists('counties', 'id')],
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:today',
            'occupation' => 'nullable|string|max:255',
            'membership_type' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:15',
            'terms_accepted' => 'required|boolean',
            'marketing_consent' => 'sometimes|boolean',
            'interests' => 'nullable|array',
            'bio' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get the body parameters for the documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'county_id' => [
                'description' => 'The ID of the county (from counties table)',
                'example' => 1,
            ],
            'first_name' => [
                'description' => 'User first name',
                'example' => 'John',
            ],
            'last_name' => [
                'description' => 'User last name',
                'example' => 'Doe',
            ],
            'phone' => [
                'description' => 'Contact phone number',
                'example' => '+254700000000',
            ],
            'gender' => [
                'description' => 'Gender identification',
                'example' => 'male',
            ],
            'date_of_birth' => [
                'description' => 'User date of birth (YYYY-MM-DD)',
                'example' => '1995-05-15',
            ],
            'terms_accepted' => [
                'description' => 'Whether user accepts terms and conditions',
                'example' => true,
            ],
            'marketing_consent' => [
                'description' => 'Whether user receives marketing emails',
                'example' => false,
            ],
            'interests' => [
                'description' => 'Array of user interest areas',
                'example' => ['robotics', 'software'],
            ],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'county_id.required' => 'County is required',
            'county_id.exists' => 'The selected county is invalid',
            'terms_accepted.required' => 'You must accept the terms and conditions',
            'date_of_birth.before' => 'Date of birth must be in the past',
        ];
    }
}
