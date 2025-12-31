<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RemoveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => 'required|string|exists:roles,name',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'role' => [
                'description' => 'Role name to remove',
                'example' => 'admin',
            ],
        ];
    }
}
