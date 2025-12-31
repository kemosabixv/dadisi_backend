<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdatePrivacySettingsRequest
 *
 * Validates update requests for user privacy settings.
 *
 * @bodyParam public_profile_enabled boolean optional Enable/disable public profile.
 * @bodyParam public_bio string optional Public bio text (max 500 chars).
 * @bodyParam show_email boolean optional Show email on public profile.
 * @bodyParam show_location boolean optional Show location on public profile.
 * @bodyParam show_join_date boolean optional Show join date on public profile.
 * @bodyParam show_post_count boolean optional Show post count on public profile.
 * @bodyParam show_interests boolean optional Show interests on public profile.
 * @bodyParam show_occupation boolean optional Show occupation on public profile.
 */
class UpdatePrivacySettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'public_profile_enabled' => ['sometimes', 'boolean'],
            'public_bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'show_email' => ['sometimes', 'boolean'],
            'show_location' => ['sometimes', 'boolean'],
            'show_join_date' => ['sometimes', 'boolean'],
            'show_post_count' => ['sometimes', 'boolean'],
            'show_interests' => ['sometimes', 'boolean'],
            'show_occupation' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'public_bio.max' => 'The public bio cannot exceed 500 characters.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'public_profile_enabled' => ['description' => 'Enable/disable public profile', 'example' => true],
            'public_bio' => ['description' => 'Public bio text (max 500 chars)', 'example' => 'Software developer passionate about community tech.'],
            'show_email' => ['description' => 'Show email on public profile', 'example' => false],
            'show_location' => ['description' => 'Show location on public profile', 'example' => true],
            'show_join_date' => ['description' => 'Show join date on public profile', 'example' => true],
            'show_post_count' => ['description' => 'Show post count on public profile', 'example' => true],
            'show_interests' => ['description' => 'Show interests on public profile', 'example' => true],
            'show_occupation' => ['description' => 'Show occupation on public profile', 'example' => false],
        ];
    }
}
