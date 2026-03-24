<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateSubscriptionPaymentRequest extends FormRequest
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
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_period' => ['nullable', 'in:month,year'],
            'phone' => ['nullable', 'string', 'regex:/^254\d{9}$/'],
            'payment_method' => ['nullable', 'string', 'in:pesapal,mock'],
        ];
    }

    /**
     * Get the body parameters for the documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'plan_id' => [
                'description' => 'The ID of the plan to subscribe to',
                'example' => 1,
            ],
            'billing_period' => [
                'description' => 'Billing interval (month or year)',
                'example' => 'year',
            ],
            'phone' => [
                'description' => 'Phone number for payment (format: 254xxxxxxxxx)',
                'example' => '254700000000',
            ],
            'payment_method' => [
                'description' => 'Payment processor to use (pesapal or mock)',
                'example' => 'pesapal',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'Plan ID is required.',
            'plan_id.exists' => 'The selected plan does not exist.',
            'billing_period.in' => 'Billing period must be either month or year.',
            'phone.regex' => 'Phone number must be in format 254xxxxxxxxx.',
            'payment_method.in' => 'Payment method must be either pesapal or mock.',
        ];
    }
}
