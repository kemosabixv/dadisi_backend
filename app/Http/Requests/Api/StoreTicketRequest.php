<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
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
}
