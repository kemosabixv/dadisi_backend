<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam title string optional Max: 255
 * @bodyParam content string optional Post content
 * @bodyParam slug string optional Max: 255, unique
 * @bodyParam excerpt string optional Max: 500
 * @bodyParam status string optional in:draft,published
 * @bodyParam category_ids array optional Category IDs
 * @bodyParam tag_ids array optional Tag IDs
 * @bodyParam media_ids array optional Media IDs
 * @bodyParam hero_image_path string optional Max: 500
 */
class UpdatePostRequest extends FormRequest
{
    public function authorize()
    {
        return true;
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
            'category_ids' => 'sometimes|array|exists:categories,id',
            'tag_ids' => 'sometimes|array|exists:tags,id',
            'media_ids' => 'sometimes|array|exists:media,id',
            'hero_image_path' => 'sometimes|string|max:500',
        ];
    }
}
