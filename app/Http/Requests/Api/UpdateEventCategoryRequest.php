<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add RBAC if needed
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id ?? null;
        return [
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255|unique:event_categories,slug,' . $categoryId,
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'parent_id' => 'nullable|exists:event_categories,id',
            'image_path' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Category name', 'example' => 'Conferences'],
            'slug' => ['description' => 'URL-safe slug', 'example' => 'conferences'],
            'description' => ['description' => 'Category description', 'example' => 'Large-scale tech conferences'],
            'color' => ['description' => 'Color for UI display', 'example' => '#10B981'],
            'parent_id' => ['description' => 'Parent category ID for nesting', 'example' => null],
            'image_path' => ['description' => 'Path to category image', 'example' => 'categories/conferences.jpg'],
            'is_active' => ['description' => 'Whether category is active', 'example' => true],
            'sort_order' => ['description' => 'Sort order for display', 'example' => 2],
        ];
    }
}
