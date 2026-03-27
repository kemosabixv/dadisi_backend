<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemFeatureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('manage_system_features');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'default_value' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * Get the body parameters for the documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Human-readable name of the feature',
                'example' => 'Enhanced Analytics',
            ],
            'description' => [
                'description' => 'Detailed description of what the feature does',
                'example' => 'Enable advanced tracking for member engagement.',
            ],
            'default_value' => [
                'description' => 'Default setting for the feature',
                'example' => 'disabled',
            ],
            'is_active' => [
                'description' => 'Enable or disable the feature system-wide',
                'example' => true,
            ],
            'sort_order' => [
                'description' => 'Priority in settings UI',
                'example' => 10,
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.string' => 'Feature name must be a string.',
            'name.max' => 'Feature name cannot exceed 255 characters.',
            'description.string' => 'Description must be a string.',
            'default_value.string' => 'Default value must be a string.',
            'is_active.boolean' => 'Active status must be a boolean.',
            'sort_order.integer' => 'Sort order must be an integer.',
            'sort_order.min' => 'Sort order must be 0 or higher.',
        ];
    }
}
