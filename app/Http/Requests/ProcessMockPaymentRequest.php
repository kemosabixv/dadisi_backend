<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessMockPaymentRequest extends FormRequest
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
            'transaction_id' => ['required', 'string'],
            'phone' => ['required', 'string', 'regex:/^254\d{9}$/'],
        ];
    }

    /**
     * Get the body parameters for the documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'transaction_id' => [
                'description' => 'The mock transaction ID to process',
                'example' => 'MOCK-123456',
            ],
            'phone' => [
                'description' => 'Phone number associated with the mock payment',
                'example' => '254700000000',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'transaction_id.required' => 'Transaction ID is required.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Phone number must be in format 254xxxxxxxxx.',
        ];
    }
}
