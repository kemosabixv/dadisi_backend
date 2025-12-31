<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class BulkRemoveRoleRequest extends FormRequest
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
                'description' => 'Array of user IDs to remove role from',
                'example' => [1, 2, 3],
            ],
            'role' => [
                'description' => 'Role name to remove',
                'example' => 'admin',
            ],
        ];
    }
}
