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
            // Generic fields
            'event_type' => 'nullable|string',
            'transaction_id' => 'nullable|string',
            'status' => 'nullable|string',
            'amount' => 'nullable|numeric',
            
            // Pesapal V3 specific fields
            'OrderTrackingId' => 'nullable|string',
            'OrderMerchantReference' => 'nullable|string',
            'OrderNotificationType' => 'nullable|string',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'event_type' => ['description' => 'Type of webhook event', 'example' => 'PAYMENT_COMPLETED'],
            'transaction_id' => ['description' => 'Transaction ID from gateway', 'example' => 'txn_abc123'],
            'OrderTrackingId' => ['description' => 'Pesapal Order Tracking ID', 'example' => 'b945e4af-80a5-4ec1-8706-e03f8332fb04'],
            'OrderMerchantReference' => ['description' => 'Merchant Reference (Order ID)', 'example' => 'customer_123'],
            'OrderNotificationType' => ['description' => 'Notification type (IPNCHANGE, RECURRING)', 'example' => 'IPNCHANGE'],
        ];
    }
}
