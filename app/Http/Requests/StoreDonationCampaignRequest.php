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
