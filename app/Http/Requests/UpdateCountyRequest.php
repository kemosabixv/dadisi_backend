<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCountyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('update county');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $county = $this->route('county');
        $countyId = is_object($county) ? $county->id : $county;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:counties,name,' . $countyId],
        ];
    }

    /**
     * Get the body parameters for the documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'The new name for the county',
                'example' => 'Nairobi City',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'County name is required when updating.',
            'name.unique' => 'This county already exists.',
            'name.max' => 'County name cannot exceed 255 characters.',
        ];
    }
}
