<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1|max:50',
            'user_ids.*' => 'integer|exists:users,id',
            'data' => 'required|array',
            'data.username' => 'sometimes|string|max:255',
            'data.email' => 'sometimes|email',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'user_ids' => [
                'description' => 'Array of user IDs to update',
                'example' => [1, 2, 3],
            ],
            'data' => [
                'description' => 'Data to update for users',
                'example' => ['username' => 'newusername', 'email' => 'newemail@example.com'],
            ],
        ];
    }
}
