<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class BulkAssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'integer|exists:users,id',
            'role' => 'required|string|exists:roles,name',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'user_ids' => [
                'description' => 'Array of user IDs to assign role to',
                'example' => [1, 2, 3],
            ],
            'role' => [
                'description' => 'Role name to assign',
                'example' => 'admin',
            ],
        ];
    }
}
