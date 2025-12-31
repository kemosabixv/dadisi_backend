<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SyncRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'roles' => [
                'description' => 'Array of role names to sync',
                'example' => ['admin', 'moderator'],
            ],
        ];
    }
}
