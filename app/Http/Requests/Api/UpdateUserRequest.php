<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'username' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('users')->ignore($userId)
            ],
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users')->ignore($userId)
            ],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'username' => [
                'description' => 'New username for the user',
                'example' => 'new_username',
            ],
            'email' => [
                'description' => 'New email address for the user',
                'example' => 'newemail@example.com',
            ],
        ];
    }
}
