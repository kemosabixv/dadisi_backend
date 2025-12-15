<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserPaymentMethodRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() != null;
    }

    public function rules()
    {
        return [
            'type' => 'required|string|in:phone_pattern,card,pesapal',
            'identifier' => 'nullable|string|max:255',
            'label' => 'nullable|string|max:255',
            'is_primary' => 'nullable|boolean',
        ];
    }
}
