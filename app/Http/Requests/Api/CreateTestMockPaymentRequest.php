<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateTestMockPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:500',
            'user_email' => 'nullable|email',
            'payment_type' => 'nullable|string|in:test,subscription,donation,event',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'amount' => ['description' => 'Payment amount in KES', 'example' => 1000.00],
            'description' => ['description' => 'Payment description', 'example' => 'Test payment for subscription'],
            'user_email' => ['description' => 'User email for receipt', 'example' => 'test@example.com'],
            'payment_type' => ['description' => 'Type of payment', 'example' => 'subscription'],
        ];
    }
}
