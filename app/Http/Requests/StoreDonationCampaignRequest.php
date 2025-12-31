<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDonationCampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\DonationCampaign::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'goal_amount' => ['nullable', 'numeric', 'min:0'],
            'minimum_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'in:KES,USD'],
            'hero_image' => ['nullable', 'image', 'max:5120'], // 5MB
            'county_id' => ['nullable', 'exists:counties,id'],
            'starts_at' => ['nullable', 'date', 'after_or_equal:today'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['sometimes', 'in:draft,active'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Campaign title', 'example' => 'Emergency Relief Fund'],
            'description' => ['description' => 'Full campaign description', 'example' => 'Help us support affected communities'],
            'short_description' => ['description' => 'Short campaign description', 'example' => 'Relief campaign'],
            'goal_amount' => ['description' => 'Campaign goal amount', 'example' => 1000000],
            'minimum_amount' => ['description' => 'Minimum donation amount', 'example' => 100],
            'currency' => ['description' => 'Currency (KES or USD)', 'example' => 'KES'],
            'hero_image' => ['description' => 'Campaign hero image file (optional)', 'example' => null],
            'county_id' => ['description' => 'Associated county ID', 'example' => 1],
            'starts_at' => ['description' => 'Campaign start date', 'example' => '2025-01-01'],
            'ends_at' => ['description' => 'Campaign end date', 'example' => '2025-01-31'],
            'status' => ['description' => 'Campaign status', 'example' => 'draft'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Campaign title is required.',
            'title.max' => 'Campaign title cannot exceed 255 characters.',
            'description.required' => 'Campaign description is required.',
            'short_description.max' => 'Short description cannot exceed 500 characters.',
            'goal_amount.min' => 'Goal amount must be a positive number.',
            'minimum_amount.min' => 'Minimum amount must be a positive number.',
            'currency.in' => 'Currency must be KES or USD.',
            'hero_image.image' => 'Hero image must be an image file.',
            'hero_image.max' => 'Hero image must not exceed 5MB.',
            'county_id.exists' => 'Selected county does not exist.',
            'starts_at.after_or_equal' => 'Start date must be today or in the future.',
            'ends_at.after' => 'End date must be after the start date.',
        ];
    }
}
