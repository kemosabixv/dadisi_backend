<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_id' => 'required|string',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'transaction_id' => ['description' => 'The Pesapal transaction ID to verify', 'example' => 'txn_abc123def456'],
        ];
    }
}
