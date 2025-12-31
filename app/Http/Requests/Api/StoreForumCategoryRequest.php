<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreForumCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:forum_categories,slug',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Category name', 'example' => 'General Discussion'],
            'slug' => ['description' => 'URL-safe slug', 'example' => 'general-discussion'],
            'description' => ['description' => 'Category description', 'example' => 'General community discussions'],
            'icon' => ['description' => 'Icon class or name', 'example' => 'chat-bubble'],
            'order' => ['description' => 'Display order', 'example' => 1],
            'is_active' => ['description' => 'Whether category is active', 'example' => true],
        ];
    }
}
