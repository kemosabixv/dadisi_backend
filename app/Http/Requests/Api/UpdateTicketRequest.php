<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
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
            'is_active' => 'nullable|boolean',
        ];
    }
}
