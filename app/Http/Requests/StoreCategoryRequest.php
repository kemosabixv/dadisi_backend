<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_post_categories');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100|unique:categories,name',
            'slug' => 'nullable|string|max:120|unique:categories,slug',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required',
            'name.unique' => 'A category with this name already exists',
            'slug.unique' => 'This category slug is already taken',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Category name',
                'example' => 'Technology',
                'required' => true,
            ],
            'slug' => [
                'description' => 'URL-friendly slug. Auto-generated if not provided',
                'example' => 'technology',
                'required' => false,
            ],
            'description' => [
                'description' => 'Category description',
                'example' => 'Technology related posts and articles',
                'required' => false,
            ],
        ];
    }
}
