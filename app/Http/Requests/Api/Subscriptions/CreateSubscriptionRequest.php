<?php

namespace App\Http\Requests\Api\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;

class CreateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'plan_id' => 'required|integer|exists:plans,id',
            'billing_period' => 'nullable|in:month,year',
            'phone' => 'nullable|string|regex:/^254\d{9}$/',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'Please select a subscription plan',
            'plan_id.exists' => 'The selected plan does not exist',
            'billing_period.in' => 'Billing period must be monthly or yearly',
            'phone.regex' => 'Phone number must be in format 254XXXXXXXXX',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'plan_id' => [
                'description' => 'The subscription plan ID',
                'example' => 1,
            ],
            'billing_period' => [
                'description' => 'Billing period: month or year',
                'example' => 'month',
            ],
            'phone' => [
                'description' => 'Phone number for payment (format: 254XXXXXXXXX)',
                'example' => '254712345678',
            ],
            'notes' => [
                'description' => 'Additional notes about the subscription',
                'example' => 'Upgrading from free plan',
            ],
        ];
    }
}
