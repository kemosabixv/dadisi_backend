<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateEventOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add RBAC if needed
    }

    public function rules(): array
    {
        $user = $this->user();
        $rules = [
            'quantity' => 'required|integer|min:1|max:10',
            'phone' => 'nullable|string|max:20',
            'promo_code' => 'nullable|string|max:50',
        ];
        if (!$user) {
            $rules['name'] = 'required|string|max:255';
            $rules['email'] = 'required|email|max:255';
        }
        return $rules;
    }

    public function bodyParameters(): array
    {
        return [
            'quantity' => ['description' => 'Number of tickets to purchase', 'example' => 2],
            'phone' => ['description' => 'Contact phone number', 'example' => '254712345678'],
            'promo_code' => ['description' => 'Promotional code for discount', 'example' => 'PROMO2025'],
            'name' => ['description' => 'Attendee name (for guests)', 'example' => 'John Doe'],
            'email' => ['description' => 'Attendee email (for guests)', 'example' => 'john@example.com'],
        ];
    }
}
