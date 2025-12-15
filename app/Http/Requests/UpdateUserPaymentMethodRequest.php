<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserPaymentMethodRequest extends FormRequest
{
    public function authorize()
    {
        // ensure user owns the method in controller as well; basic auth check here
        return $this->user() != null;
    }

    public function rules()
    {
        return [
            'label' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'is_primary' => 'nullable|boolean',
        ];
    }
}
