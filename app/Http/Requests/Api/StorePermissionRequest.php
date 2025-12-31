<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:permissions,name',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Name of the permission',
                'example' => 'create-posts',
            ],
        ];
    }
}
