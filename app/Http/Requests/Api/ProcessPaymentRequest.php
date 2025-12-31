<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_id' => 'required|string',
            'order_id' => 'required|integer|exists:plan_subscriptions,id',
            'phone' => 'required|string|regex:/^254\d{9}$/',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'transaction_id' => ['description' => 'The payment transaction ID', 'example' => 'txn_abc123'],
            'order_id' => ['description' => 'The subscription/order ID', 'example' => 1],
            'phone' => ['description' => 'M-Pesa phone number (format: 254XXXXXXXXX)', 'example' => '254712345678'],
        ];
    }
}
