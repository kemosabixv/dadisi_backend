<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|in:KES,USD',
            'quantity' => 'nullable|integer|min:1',
            'order_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Ticket name/type', 'example' => 'VIP Seating'],
            'description' => ['description' => 'Ticket description', 'example' => 'VIP seating with front row access'],
            'price' => ['description' => 'Ticket price', 'example' => 3000.00],
            'currency' => ['description' => 'Price currency', 'example' => 'KES'],
            'quantity' => ['description' => 'Number of tickets available', 'example' => 40],
            'order_limit' => ['description' => 'Maximum tickets per order', 'example' => 4],
            'is_active' => ['description' => 'Whether ticket is active for sale', 'example' => true],
        ];
    }
}
