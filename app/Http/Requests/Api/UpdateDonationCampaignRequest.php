<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam title string optional Max: 200
 * @bodyParam description string optional Max: 2000
 * @bodyParam goal_amount numeric optional Min: 1
 * @bodyParam currency string optional Must be KES or USD
 * @bodyParam county_id integer optional Must exist in counties table
 * @bodyParam start_date date optional
 * @bodyParam end_date date optional Must be after or equal to start_date
 * @bodyParam hero_image file optional
 */
class UpdateDonationCampaignRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'string', 'max:2000'],
            'goal_amount' => ['sometimes', 'numeric', 'min:1'],
            'currency' => ['sometimes', 'string', 'in:KES,USD'],
            'county_id' => ['sometimes', 'exists:counties,id'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'hero_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
        ];
    }
}
