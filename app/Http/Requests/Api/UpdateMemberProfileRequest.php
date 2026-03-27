<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'county_id' => ['sometimes', 'integer', Rule::exists('counties', 'id')],
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone_number' => 'sometimes|string|max:30',
            'gender' => 'sometimes|in:male,female,other',
            'date_of_birth' => 'sometimes|date|before:today|nullable',
            'occupation' => 'sometimes|string|max:255',
            'membership_type' => 'sometimes|string',
            'emergency_contact_name' => 'sometimes|string|max:255',
            'emergency_contact_phone' => 'sometimes|string|max:15',
            'marketing_consent' => 'sometimes|boolean',
            'interests' => 'sometimes|array',
            'bio' => 'sometimes|string|max:1000',
            'public_profile_enabled' => 'sometimes|boolean',
            'public_bio' => 'sometimes|string|max:1000',
            'show_email' => 'sometimes|boolean',
            'show_location' => 'sometimes|boolean',
            'show_join_date' => 'sometimes|boolean',
            'show_post_count' => 'sometimes|boolean',
            'show_interests' => 'sometimes|boolean',
            'show_occupation' => 'sometimes|boolean',
            'sub_county' => 'sometimes|string|max:100',
            'ward' => 'sometimes|string|max:100',
            'display_full_name' => 'sometimes|boolean',
            'display_age' => 'sometimes|boolean',
            'prefix' => 'sometimes|string|max:20|nullable',
            'public_role' => 'sometimes|string|max:100|nullable',
            'experience' => 'sometimes|array',
            'experience_visible' => 'sometimes|boolean',
            'education' => 'sometimes|array',
            'education_visible' => 'sometimes|boolean',
            'skills' => 'sometimes|array',
            'skills_visible' => 'sometimes|boolean',
            'achievements' => 'sometimes|array',
            'achievements_visible' => 'sometimes|boolean',
            'certifications' => 'sometimes|array',
            'certifications_visible' => 'sometimes|boolean',
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
            'phone_number' => [
                'description' => 'Primary contact phone number',
                'example' => '+254700000000',
            ],
            'gender' => [
                'description' => 'Gender identification',
                'example' => 'male',
            ],
            'date_of_birth' => [
                'description' => 'User date of birth',
                'example' => '1990-01-01',
            ],
            'occupation' => [
                'description' => 'Current occupation or job title',
                'example' => 'Software Engineer',
            ],
            'membership_type' => [
                'description' => 'Type of membership',
                'example' => 'individual',
            ],
            'emergency_contact_name' => [
                'description' => 'Name of emergency contact person',
                'example' => 'Jane Smith',
            ],
            'emergency_contact_phone' => [
                'description' => 'Phone number of emergency contact',
                'example' => '+254711111111',
            ],
            'marketing_consent' => [
                'description' => 'Whether user agrees to marketing communications',
                'example' => true,
            ],
            'interests' => [
                'description' => 'Array of interest tags',
                'example' => ['technology', 'biology', 'education'],
            ],
            'bio' => [
                'description' => 'Personal biography',
                'example' => 'A passionate bio-hacker from Nairobi.',
            ],
            'public_profile_enabled' => [
                'description' => 'Toggle public visibility of the profile',
                'example' => true,
            ],
            'public_bio' => [
                'description' => 'Biography displayed on public profile',
                'example' => 'Bio-technologist and community leader.',
            ],
            'show_email' => [
                'description' => 'Show email address on public profile',
                'example' => false,
            ],
            'show_location' => [
                'description' => 'Show location/county on public profile',
                'example' => true,
            ],
            'sub_county' => [
                'description' => 'Sub-county of residence',
                'example' => 'Westlands',
            ],
            'ward' => [
                'description' => 'Ward of residence',
                'example' => 'Kitisuru',
            ],
            'experience' => [
                'description' => 'JSON array of professional experience',
                'example' => [['title' => 'Researcher', 'company' => 'Lab X', 'year' => '2023']],
            ],
            'skills' => [
                'description' => 'JSON array of technical skills',
                'example' => ['PCR', 'CRISPR', 'Next.js'],
            ],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'county_id.exists' => 'The selected county is invalid',
            'date_of_birth.before' => 'Date of birth must be in the past',
        ];
    }
}
