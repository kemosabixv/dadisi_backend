<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('manage_system_settings');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Allow any key-value pairs as settings are dynamic
        // Each setting follows pattern: key => value where key is dot-notated (e.g., pesapal.consumer_key)
        return $this->getValidationRulesForSettings();
    }

    /**
     * Get the body parameters for the documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'key' => [
                'description' => 'The setting key in dot-notation (e.g., site.name, pesapal.consumer_key)',
                'example' => 'site.name',
            ],
            'value' => [
                'description' => 'The value to assign to the setting',
                'example' => 'Dadisi Community Labs',
            ],
        ];
    }

    /**
     * Generate validation rules for all provided settings.
     * Since settings are dynamic, we accept any key and value type.
     */
    protected function getValidationRulesForSettings(): array
    {
        $rules = [];
        
        // For each input field, accept it as valid
        // This is permissive to allow dynamic settings with any structure
        foreach ($this->all() as $key => $value) {
            // Skip Laravel's internal fields
            if (in_array($key, ['_token', '_method'])) {
                continue;
            }
            // Accept any setting key and value
            $rules[$key] = 'sometimes';
        }
        
        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'required' => 'This field is required.',
        ];
    }
}
