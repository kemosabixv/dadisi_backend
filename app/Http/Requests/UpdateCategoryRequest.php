<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('category'));
    }

    public function rules(): array
    {
        $category = $this->route('category');
        return [
            'name' => 'string|max:100|unique:categories,name,' . $category->id,
            'slug' => 'string|max:120|unique:categories,slug,' . $category->id,
            'description' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A category with this name already exists',
            'slug.unique' => 'This category slug is already taken',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Category name',
                'example' => 'Updated Technology',
                'required' => false,
            ],
            'slug' => [
                'description' => 'URL-friendly slug',
                'example' => 'updated-technology',
                'required' => false,
            ],
            'description' => [
                'description' => 'Category description',
                'example' => 'Updated description',
                'required' => false,
            ],
        ];
    }
}
