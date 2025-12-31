<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam title string required Max: 255
 * @bodyParam content string required Post content
 * @bodyParam slug string optional Max: 255, must be unique
 * @bodyParam excerpt string optional Max: 500
 * @bodyParam status string optional in:draft,published
 * @bodyParam county_id integer optional Must exist in counties
 * @bodyParam category_ids array optional Category IDs
 * @bodyParam tag_ids array optional Tag IDs
 * @bodyParam hero_image_path string optional Max: 500
 * @bodyParam meta_title string optional Max: 60
 * @bodyParam meta_description string optional Max: 160
 * @bodyParam is_featured boolean optional
 */
class StoreAuthorPostRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'slug' => 'nullable|string|max:255|unique:posts,slug',
            'excerpt' => 'nullable|string|max:500',
            'status' => 'in:draft,published',
            'county_id' => 'nullable|exists:counties,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'hero_image_path' => 'nullable|string|max:500',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'is_featured' => 'boolean',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Post title', 'example' => 'My First Blog Post'],
            'content' => ['description' => 'Full post content in HTML', 'example' => '<p>This is my blog post content.</p>'],
            'slug' => ['description' => 'URL-friendly slug (auto-generated from title if not provided)', 'example' => 'my-first-blog-post'],
            'excerpt' => ['description' => 'Brief excerpt for previews', 'example' => 'A brief introduction to my blog post.'],
            'status' => ['description' => 'Post status: draft or published', 'example' => 'draft'],
            'county_id' => ['description' => 'ID of the associated county', 'example' => 35],
            'category_ids' => ['description' => 'Array of category IDs', 'example' => [1, 2]],
            'tag_ids' => ['description' => 'Array of tag IDs', 'example' => [1, 3]],
            'hero_image_path' => ['description' => 'Path to the hero image', 'example' => 'posts/hero.jpg'],
            'meta_title' => ['description' => 'SEO meta title', 'example' => 'My Blog Post - Dadisi'],
            'meta_description' => ['description' => 'SEO meta description', 'example' => 'Read about my experiences in this blog post.'],
            'is_featured' => ['description' => 'Whether the post should be featured', 'example' => false],
        ];
    }
}
