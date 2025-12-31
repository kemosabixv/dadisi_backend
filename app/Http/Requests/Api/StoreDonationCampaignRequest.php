<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam title string required Max: 200
 * @bodyParam description string required Max: 2000
 * @bodyParam goal_amount numeric required Min: 1
 * @bodyParam currency string required Must be KES or USD
 * @bodyParam county_id integer required Must exist in counties table
 * @bodyParam start_date date required
 * @bodyParam end_date date required
 * @bodyParam hero_image file optional
 */
class StoreDonationCampaignRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:2000'],
            'goal_amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['required', 'string', 'in:KES,USD'],
            'county_id' => ['required', 'exists:counties,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'hero_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
        ];
    }
}
