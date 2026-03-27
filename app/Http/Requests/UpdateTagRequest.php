<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tag'));
    }

    public function rules(): array
    {
        $tag = $this->route('tag');
        $tagId = is_object($tag) ? $tag->id : $tag;

        return [
            'name' => 'string|max:100|unique:tags,name,' . $tagId,
            'slug' => 'string|max:120|unique:tags,slug,' . $tagId,
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A tag with this name already exists',
            'slug.unique' => 'This tag slug is already taken',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Tag name',
                'example' => 'Updated Tag',
                'required' => false,
            ],
            'slug' => [
                'description' => 'URL-friendly slug',
                'example' => 'updated-tag',
                'required' => false,
            ],
        ];
    }
}
