<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateTestPesapalPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission check is handled by middleware but we can add more here if needed
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:500',
            'user_email' => 'required|email',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'amount' => ['description' => 'Payment amount in KES', 'example' => 1000.00],
            'description' => ['description' => 'Payment description', 'example' => 'Real Pesapal Sandbox Test'],
            'user_email' => ['description' => 'User email for Pesapal billing', 'example' => 'test@example.com'],
            'first_name' => ['description' => 'Payer first name', 'example' => 'John'],
            'last_name' => ['description' => 'Payer last name', 'example' => 'Doe'],
            'phone' => ['description' => 'Payer phone number', 'example' => '254700000000'],
        ];
    }
}
