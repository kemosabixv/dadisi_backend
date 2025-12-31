<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DeleteSelfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'password' => [
                'description' => 'User password for verification',
                'example' => 'password123',
            ],
            'reason' => [
                'description' => 'Reason for account deletion',
                'example' => 'No longer interested',
            ],
        ];
    }
}
