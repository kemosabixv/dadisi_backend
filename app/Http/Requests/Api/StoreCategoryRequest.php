<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam name string required Max: 100, unique
 * @bodyParam slug string optional Max: 120, unique
 * @bodyParam description string optional Max: 500
 */
class StoreCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:100|unique:categories,name',
            'slug' => 'nullable|string|max:120|unique:categories,slug',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'The category name',
                'example' => 'Technology',
            ],
            'slug' => [
                'description' => 'URL-friendly category slug (auto-generated if not provided)',
                'example' => 'technology',
            ],
            'description' => [
                'description' => 'Category description',
                'example' => 'Posts about technology and innovation',
            ],
        ];
    }
}
