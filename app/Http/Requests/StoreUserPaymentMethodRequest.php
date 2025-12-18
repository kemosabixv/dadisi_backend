<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserPaymentMethodRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() != null;
    }

    public function rules()
    {
        return [
            'type' => 'required|string|in:phone_pattern,card,pesapal',
            'identifier' => 'nullable|string|max:255',
            'label' => 'nullable|string|max:255',
            'is_primary' => 'nullable|boolean',
        ];
    }

    public function bodyParameters()
    {
        return [
            'type' => [
                'description' => 'The type of payment method (phone_pattern usually implies MPESA).',
                'example' => 'phone_pattern',
            ],
            'identifier' => [
                'description' => 'The unique identifier for the payment method (e.g., partial phone number or masked card). Sensitive data should not be stored here.',
                'example' => '2547***123',
            ],
            'label' => [
                'description' => 'A user-friendly label for this payment method.',
                'example' => 'My Safaricom Line',
            ],
            'is_primary' => [
                'description' => 'Whether to set this as the default payment method.',
                'example' => true,
            ],
        ];
    }
}
