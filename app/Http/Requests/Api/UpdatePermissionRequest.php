<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionId = $this->route('permission')?->id;

        return [
            'name' => 'sometimes|required|string|max:255|unique:permissions,name,' . $permissionId,
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Name of the permission',
                'example' => 'edit-posts',
            ],
        ];
    }
}
