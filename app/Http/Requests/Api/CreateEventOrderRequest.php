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
        $user = \Illuminate\Support\Facades\Auth::user();

        // If an Authorization header is present but authentication failed,
        // we should throw an exception to return 401 instead of falling through to guest rules (which causes 422)
        if (!$user && $this->header('Authorization')) {
            throw new \Illuminate\Auth\AuthenticationException('Unauthenticated.');
        }

        $rules = [
            'ticket_id' => 'required|exists:tickets,id',
            'quantity' => 'required|integer|min:1|max:10',
            'phone' => 'nullable|string|max:20',
            'promo_code' => 'nullable|string|max:50',
            'is_waitlist_action' => 'nullable|boolean',
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
            'ticket_id' => ['description' => 'ID of the ticket tier being purchased', 'example' => 1],
            'quantity' => ['description' => 'Number of tickets to purchase', 'example' => 2],
            'phone' => ['description' => 'Contact phone number', 'example' => '254712345678'],
            'promo_code' => ['description' => 'Promotional code for discount', 'example' => 'PROMO2025'],
            'name' => ['description' => 'Attendee name (for guests)', 'example' => 'John Doe'],
            'email' => ['description' => 'Attendee email (for guests)', 'example' => 'john@example.com'],
        ];
    }
}
