<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetPublicProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Publicly accessible; logic and super_admin exclusion handled in service/resource
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'username' => 'required|string',
        ];
    }

    /**
     * Get the query parameters for the documentation.
     */
    public function queryParameters(): array
    {
        return [
            'username' => [
                'description' => 'The unique username of the member',
                'example' => 'johndoe',
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'username' => $this->route('username'),
        ]);
    }
}
