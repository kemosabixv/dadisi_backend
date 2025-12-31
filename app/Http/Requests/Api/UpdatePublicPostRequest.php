<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam title string optional Max: 255
 * @bodyParam slug string optional Max: 255, unique
 * @bodyParam excerpt string optional Max: 500
 * @bodyParam content string optional Post content
 * @bodyParam status string optional in:draft,published
 * @bodyParam category_ids array optional Category IDs
 * @bodyParam tag_ids array optional Tag IDs
 */
class UpdatePublicPostRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        $postId = $this->route('post')?->id;
        
        return [
            'title' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:posts,slug,' . $postId,
            'excerpt' => 'sometimes|string|max:500',
            'content' => 'sometimes|string',
            'status' => 'sometimes|in:draft,published',
            'category_ids' => 'sometimes|array|exists:categories,id',
            'tag_ids' => 'sometimes|array|exists:tags,id',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Post title', 'example' => 'My Updated Post'],
            'slug' => ['description' => 'URL-friendly slug', 'example' => 'my-updated-post'],
            'excerpt' => ['description' => 'Brief excerpt', 'example' => 'Updated excerpt for my post.'],
            'content' => ['description' => 'Full post content', 'example' => '<p>Updated content.</p>'],
            'status' => ['description' => 'Post status', 'example' => 'published'],
            'category_ids' => ['description' => 'Array of category IDs', 'example' => [1]],
            'tag_ids' => ['description' => 'Array of tag IDs', 'example' => [2]],
        ];
    }
}
