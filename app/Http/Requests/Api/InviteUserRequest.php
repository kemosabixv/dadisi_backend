<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
            'username' => 'required|string|max:255|unique:users,username',
            'roles' => 'sometimes|array',
            'roles.*' => 'string|exists:roles,name',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'Email address of user to invite',
                'example' => 'user@example.com',
            ],
            'username' => [
                'description' => 'Username for the new user',
                'example' => 'john_doe',
            ],
            'roles' => [
                'description' => 'Array of role names to assign',
                'example' => ['member', 'contributor'],
            ],
        ];
    }
}
