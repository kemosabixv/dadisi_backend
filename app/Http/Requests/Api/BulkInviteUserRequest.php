<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class BulkInviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'users' => 'required|array|min:1|max:50',
            'users.*.email' => 'required|email',
            'users.*.username' => 'required|string',
            'users.*.roles' => 'sometimes|array',
            'users.*.roles.*' => 'string|exists:roles,name',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'users' => [
                'description' => 'Array of users to invite',
                'example' => [['email' => 'user@example.com', 'username' => 'username', 'roles' => ['member']]],
            ],
        ];
    }
}
