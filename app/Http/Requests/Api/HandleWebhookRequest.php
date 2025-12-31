<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class HandleWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_type' => 'required|string',
            'transaction_id' => 'required|string',
            'status' => 'required|in:completed,pending,failed',
            'amount' => 'nullable|numeric',
            'order_tracking_id' => 'nullable|string',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'event_type' => ['description' => 'Type of webhook event', 'example' => 'PAYMENT_COMPLETED'],
            'transaction_id' => ['description' => 'Pesapal transaction ID', 'example' => 'txn_abc123def456'],
            'status' => ['description' => 'Payment status', 'example' => 'completed'],
            'amount' => ['description' => 'Payment amount', 'example' => 1500.00],
            'order_tracking_id' => ['description' => 'Order tracking reference', 'example' => 'order_xyz789'],
        ];
    }
}
