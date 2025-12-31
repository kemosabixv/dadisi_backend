<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_id' => 'required|string', // Support either transaction_id or payment_id (legacy)
            'payment_id' => 'nullable|integer',
            'reason' => 'required|string|max:500',
            'amount' => 'nullable|numeric|min:0.01',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'transaction_id' => ['description' => 'The payment transaction ID to refund', 'example' => 'txn_abc123'],
            'reason' => ['description' => 'Reason for the refund request', 'example' => 'Event was cancelled by organizer'],
            'amount' => ['description' => 'Partial refund amount (optional, full refund if not specified)', 'example' => 500.00],
        ];
    }
}
