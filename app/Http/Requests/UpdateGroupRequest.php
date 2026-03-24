<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('update group');
    }

    /**
     * Get the validation rules that apply to the request.
    public function rules(): array
    {
        $group = $this->route('group');
        $groupId = is_object($group) ? $group->id : $group;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'county_id' => ['sometimes', 'required', 'exists:counties,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:groups,slug,' . $groupId],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Group name is required when updating.',
            'name.max' => 'Group name cannot exceed 255 characters.',
            'county_id.required' => 'County is required when updating.',
            'county_id.exists' => 'The selected county does not exist.',
            'slug.unique' => 'This group slug already exists.',
            'slug.max' => 'Group slug cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
        ];
    }
}
