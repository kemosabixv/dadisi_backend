<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Post::class);
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:posts,slug',
            'excerpt' => 'required|string|max:500',
            'content' => 'required|string',
            'county_id' => 'required|exists:counties,id',
            'status' => 'required|in:draft,published',
            'is_featured' => 'boolean',
            'meta_title' => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:160',
            'category_ids' => 'required|array|min:1|exists:categories,id',
            'tag_ids' => 'nullable|array|exists:tags,id',
            'media_ids' => 'nullable|array|exists:media,id',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Post title is required',
            'title.max' => 'Post title must not exceed 255 characters',
            'excerpt.required' => 'Post excerpt is required',
            'excerpt.max' => 'Post excerpt must not exceed 500 characters',
            'content.required' => 'Post content is required',
            'county_id.required' => 'County selection is required',
            'category_ids.required' => 'At least one category is required',
            'category_ids.min' => 'At least one category is required',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'title' => [
                'description' => 'Post title',
                'example' => 'Getting Started with Laravel',
                'required' => true,
            ],
            'excerpt' => [
                'description' => 'Short post excerpt',
                'example' => 'Learn the basics of Laravel framework',
                'required' => true,
            ],
            'content' => [
                'description' => 'Full post content',
                'example' => 'Laravel is a web application framework...',
                'required' => true,
            ],
            'county_id' => [
                'description' => 'County ID for the post',
                'example' => 1,
                'required' => true,
            ],
            'status' => [
                'description' => 'Post status (draft or published)',
                'example' => 'published',
                'required' => true,
            ],
            'slug' => [
                'description' => 'URL-friendly slug. Auto-generated if not provided',
                'example' => 'getting-started-with-laravel',
                'required' => false,
            ],
            'is_featured' => [
                'description' => 'Whether the post is featured',
                'example' => false,
                'required' => false,
            ],
            'meta_title' => [
                'description' => 'SEO meta title',
                'example' => 'Getting Started with Laravel - Best Practices',
                'required' => false,
            ],
            'meta_description' => [
                'description' => 'SEO meta description',
                'example' => 'Learn Laravel framework from basics to advanced concepts',
                'required' => false,
            ],
            'category_ids' => [
                'description' => 'Array of category IDs (at least one required)',
                'example' => [1, 2],
                'required' => true,
            ],
            'tag_ids' => [
                'description' => 'Array of tag IDs',
                'example' => [1, 2, 3],
                'required' => false,
            ],
            'media_ids' => [
                'description' => 'Array of media IDs for images/files',
                'example' => [1, 2],
                'required' => false,
            ],
        ];
    }
}
