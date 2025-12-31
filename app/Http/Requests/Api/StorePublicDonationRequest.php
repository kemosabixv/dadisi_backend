<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam amount numeric required Minimum: 1
 * @bodyParam currency string optional Must be KES or USD
 * @bodyParam first_name string required Max: 100
 * @bodyParam last_name string required Max: 100
 * @bodyParam email string required Valid email, max: 191
 * @bodyParam phone_number string optional Max: 30
 * @bodyParam message string optional Max: 1000
 * @bodyParam county_id integer optional Must exist in counties table
 */
class StorePublicDonationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['sometimes', 'string', 'in:KES,USD'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:1000'],
            'county_id' => ['nullable', 'exists:counties,id'],
        ];
    }

    public function bodyParameters()
    {
        return [
            'amount' => [
                'description' => 'Donation amount',
                'example' => 5000,
            ],
            'currency' => [
                'description' => 'Currency code (KES or USD)',
                'example' => 'KES',
            ],
            'first_name' => [
                'description' => 'Donor first name',
                'example' => 'Jane',
            ],
            'last_name' => [
                'description' => 'Donor last name',
                'example' => 'Smith',
            ],
            'email' => [
                'description' => 'Donor email address',
                'example' => 'jane@example.com',
            ],
            'phone_number' => [
                'description' => 'Donor phone number',
                'example' => '254712345678',
            ],
            'message' => [
                'description' => 'Thank you message or donation note',
                'example' => 'Happy to help',
            ],
            'county_id' => [
                'description' => 'County ID for donor attribution',
                'example' => 1,
            ],
        ];
    }
}
