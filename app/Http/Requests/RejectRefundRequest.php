<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectRefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('manage_refunds');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get the body parameters for the documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'admin_notes' => [
                'description' => 'Reason for rejecting the refund request (sent to customer)',
                'example' => 'Rejected because the event has already taken place and the refund window is closed.',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'admin_notes.string' => 'Admin notes must be a string.',
            'admin_notes.max' => 'Admin notes cannot exceed 1000 characters.',
        ];
    }
}
