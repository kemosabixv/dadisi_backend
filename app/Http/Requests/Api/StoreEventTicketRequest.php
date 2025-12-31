<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|in:KES,USD',
            'quantity' => 'nullable|integer|min:1',
            'order_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Ticket name/type', 'example' => 'VIP Access'],
            'description' => ['description' => 'Ticket description', 'example' => 'VIP access with front row seating'],
            'price' => ['description' => 'Ticket price', 'example' => 2500.00],
            'currency' => ['description' => 'Price currency', 'example' => 'KES'],
            'quantity' => ['description' => 'Number of tickets available', 'example' => 50],
            'order_limit' => ['description' => 'Maximum tickets per order', 'example' => 5],
            'is_active' => ['description' => 'Whether ticket is active for sale', 'example' => true],
        ];
    }
}
