<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam title string optional Max: 255
 * @bodyParam content string optional Post content
 * @bodyParam slug string optional Max: 255, unique
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
class UpdateAuthorPostRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    protected function prepareForValidation()
    {
        if ($this->has('body') && !$this->has('content')) {
            $this->merge([
                'content' => $this->input('body'),
            ]);
        }
    }

    public function rules()
    {
        $postId = $this->route('post')?->id;
        
        return [
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'slug' => 'sometimes|string|max:255|unique:posts,slug,' . $postId,
            'excerpt' => 'sometimes|string|max:500',
            'status' => 'sometimes|in:draft,published',
            'county_id' => 'sometimes|exists:counties,id',
            'category_ids' => 'sometimes|array',
            'category_ids.*' => 'exists:categories,id',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'exists:tags,id',
            'hero_image_path' => 'sometimes|string|max:500',
            'meta_title' => 'sometimes|string|max:60',
            'meta_description' => 'sometimes|string|max:160',
            'is_featured' => 'sometimes|boolean',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Post title', 'example' => 'Updated Blog Post Title'],
            'content' => ['description' => 'Full post content in HTML', 'example' => '<p>Updated blog post content.</p>'],
            'slug' => ['description' => 'URL-friendly slug', 'example' => 'updated-blog-post'],
            'excerpt' => ['description' => 'Brief excerpt for previews', 'example' => 'An updated introduction.'],
            'status' => ['description' => 'Post status: draft or published', 'example' => 'published'],
            'county_id' => ['description' => 'ID of the associated county', 'example' => 35],
            'category_ids' => ['description' => 'Array of category IDs', 'example' => [1]],
            'tag_ids' => ['description' => 'Array of tag IDs', 'example' => [2]],
            'hero_image_path' => ['description' => 'Path to the hero image', 'example' => 'posts/updated-hero.jpg'],
            'meta_title' => ['description' => 'SEO meta title', 'example' => 'Updated Post - Dadisi'],
            'meta_description' => ['description' => 'SEO meta description', 'example' => 'Updated meta description for SEO.'],
            'is_featured' => ['description' => 'Whether the post should be featured', 'example' => true],
        ];
    }
}
