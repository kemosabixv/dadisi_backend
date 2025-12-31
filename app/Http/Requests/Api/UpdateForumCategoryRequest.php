<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateForumCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:forum_categories,slug,' . $categoryId,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Category name', 'example' => 'Updated Discussion Forum'],
            'slug' => ['description' => 'URL-safe slug', 'example' => 'updated-discussion-forum'],
            'description' => ['description' => 'Category description', 'example' => 'Updated description'],
            'icon' => ['description' => 'Icon class or name', 'example' => 'message-circle'],
            'order' => ['description' => 'Display order', 'example' => 2],
            'is_active' => ['description' => 'Whether category is active', 'example' => true],
        ];
    }
}
