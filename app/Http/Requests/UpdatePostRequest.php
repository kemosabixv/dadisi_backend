<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('post'));
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
        $post = $this->route('post');
        $postId = $post?->id;
        
        return [
            'title' => 'string|max:255',
            'slug' => 'string|max:255|unique:posts,slug,' . $postId,
            'excerpt' => 'string|max:500',
            'content' => 'string',
            'body' => 'string',
            'county_id' => 'nullable|exists:counties,id',
            'status' => 'in:draft,published',
            'is_featured' => 'boolean',
            'meta_title' => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:160',
            'category_ids' => 'array|min:1|exists:categories,id',
            'tag_ids' => 'nullable|array|exists:tags,id',
            'media_ids' => 'nullable|array|exists:media,id',
            'allow_comments' => 'boolean',
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
