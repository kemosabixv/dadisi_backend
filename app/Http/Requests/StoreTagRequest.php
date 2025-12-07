<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_post_tags');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100|unique:tags,name',
            'slug' => 'nullable|string|max:120|unique:tags,slug',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tag name is required',
            'name.unique' => 'A tag with this name already exists',
            'slug.unique' => 'This tag slug is already taken',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Tag name',
                'example' => 'Laravel',
                'required' => true,
            ],
            'slug' => [
                'description' => 'URL-friendly slug. Auto-generated if not provided',
                'example' => 'laravel',
                'required' => false,
            ],
        ];
    }
}
