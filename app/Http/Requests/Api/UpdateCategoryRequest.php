<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam name string optional Max: 100, unique
 * @bodyParam slug string optional Max: 120, unique
 * @bodyParam description string optional Max: 500
 */
class UpdateCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $categoryId = $this->route('category')?->id;
        
        return [
            'name' => 'sometimes|string|max:100|unique:categories,name,' . $categoryId,
            'slug' => 'sometimes|string|max:120|unique:categories,slug,' . $categoryId,
            'description' => 'sometimes|string|max:500',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'The category name',
                'example' => 'Technology Updated',
            ],
            'slug' => [
                'description' => 'URL-friendly category slug',
                'example' => 'technology-updated',
            ],
            'description' => [
                'description' => 'Category description',
                'example' => 'Updated posts about technology and innovation',
            ],
        ];
    }
}
