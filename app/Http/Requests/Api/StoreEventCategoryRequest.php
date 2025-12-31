<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add RBAC if needed
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:event_categories,slug',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'parent_id' => 'nullable|exists:event_categories,id',
            'image_path' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Category name', 'example' => 'Workshops'],
            'slug' => ['description' => 'URL-safe slug', 'example' => 'workshops'],
            'description' => ['description' => 'Category description', 'example' => 'Hands-on learning sessions'],
            'color' => ['description' => 'Color for UI display', 'example' => '#3B82F6'],
            'parent_id' => ['description' => 'Parent category ID for nesting', 'example' => null],
            'image_path' => ['description' => 'Path to category image', 'example' => 'categories/workshops.jpg'],
            'is_active' => ['description' => 'Whether category is active', 'example' => true],
            'sort_order' => ['description' => 'Sort order for display', 'example' => 1],
        ];
    }
}
