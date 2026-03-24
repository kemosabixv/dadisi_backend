<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post') ?? $this->route('slug') ?? $this->route('id');
        
        if (is_string($post) || is_numeric($post)) {
            $post = \App\Models\Post::where('id', $post)->orWhere('slug', $post)->first();
        }

        return $this->user()->can('update', $post);
    }

    protected function prepareForValidation()
    {
        if ($this->has('body') && !$this->has('content')) {
            $this->merge([
                'content' => $this->input('body'),
            ]);
        }
    }

    public function rules(): array
    {
        $post = $this->route('post') ?? $this->route('slug') ?? $this->route('id');
        
        if (is_string($post) || is_numeric($post)) {
            $post = \App\Models\Post::where('id', $post)->orWhere('slug', $post)->first();
        }
        
        $postId = $post?->id;
        
        return [
            'title' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:posts,slug,' . $postId,
            'excerpt' => 'sometimes|string|max:500',
            'content' => 'sometimes|string',
            'body' => 'sometimes|string',
            'county_id' => 'sometimes|nullable|exists:counties,id',
            'status' => 'sometimes|in:draft,published',
            'is_featured' => 'sometimes|boolean',
            'meta_title' => 'sometimes|nullable|string|max:160',
            'meta_description' => 'sometimes|nullable|string|max:160',
            'category_ids' => 'sometimes|array|min:1',
            'category_ids.*' => 'exists:categories,id',
            'tag_ids' => 'sometimes|nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'media_ids' => 'sometimes|nullable|array',
            'media_ids.*' => 'exists:media,id',
            'allow_comments' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'title.max' => 'Post title must not exceed 255 characters',
            'excerpt.max' => 'Post excerpt must not exceed 500 characters',
            'category_ids.min' => 'At least one category is required',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'title' => [
                'description' => 'Post title',
                'example' => 'Updated Post Title',
                'required' => false,
            ],
            'excerpt' => [
                'description' => 'Short post excerpt',
                'example' => 'Updated excerpt content',
                'required' => false,
            ],
            'content' => [
                'description' => 'Full post content',
                'example' => 'Updated post content...',
                'required' => false,
            ],
            'county_id' => [
                'description' => 'County ID for the post',
                'example' => 1,
                'required' => false,
            ],
            'status' => [
                'description' => 'Post status (draft or published)',
                'example' => 'published',
                'required' => false,
            ],
            'slug' => [
                'description' => 'URL-friendly slug',
                'example' => 'updated-post-title',
                'required' => false,
            ],
            'is_featured' => [
                'description' => 'Whether the post is featured',
                'example' => true,
                'required' => false,
            ],
            'meta_title' => [
                'description' => 'SEO meta title',
                'example' => 'Updated SEO Title',
                'required' => false,
            ],
            'meta_description' => [
                'description' => 'SEO meta description',
                'example' => 'Updated meta description',
                'required' => false,
            ],
            'category_ids' => [
                'description' => 'Array of category IDs (at least one required)',
                'example' => [1, 2],
                'required' => false,
            ],
            'tag_ids' => [
                'description' => 'Array of tag IDs',
                'example' => [1, 2, 3],
                'required' => false,
            ],
            'media_ids' => [
                'description' => 'Array of media IDs',
                'example' => [1, 2],
                'required' => false,
            ],
            'allow_comments' => [
                'description' => 'Whether comments are enabled for this post',
                'example' => true,
                'required' => false,
            ],
        ];
    }
}
