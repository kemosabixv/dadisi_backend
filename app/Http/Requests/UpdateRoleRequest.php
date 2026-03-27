<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('manage_roles') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $role = $this->route('role');
        $roleId = is_object($role) ? $role->id : $role;

        return [
            'name' => [
                'sometimes', 
                'required', 
                'string', 
                'max:255', 
                'unique:roles,name,' . $roleId
            ],
        ];
    }

    /**
     * Get the body parameters for the documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'The new unique name for the role',
                'example' => 'senior_editor',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Role name is required when updating.',
            'name.unique' => 'This role name already exists.',
            'name.max' => 'Role name cannot exceed 255 characters.',
        ];
    }
}
