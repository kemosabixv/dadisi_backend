<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for updating manual exchange rate
 *
 * Validates the custom USD to KES exchange rate when manually
 * overriding the system rate (e.g., for promotions or volatility management).
 */
class UpdateManualExchangeRateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rate' => 'required|numeric|min:1|max:1000', // Reasonable range for KES/USD
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'rate' => [
                'description' => 'Manual USD to KES exchange rate',
                'example' => 155.50,
            ],
        ];
    }

    /**
     * Get custom validation messages
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rate.required' => 'Exchange rate is required',
            'rate.numeric' => 'Exchange rate must be a decimal number',
            'rate.min' => 'Exchange rate must be greater than 1',
            'rate.max' => 'Exchange rate must be less than 1000',
        ];
    }

    /**
     * Get custom attribute names for validation errors
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rate' => 'exchange rate',
        ];
    }
}
