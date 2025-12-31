<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreForumTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:50|unique:forum_tags,name',
            'color' => 'nullable|string|max:7|regex:/^#[A-Fa-f0-9]{6}$/',
            'description' => 'nullable|string|max:255',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Tag name', 'example' => 'Question'],
            'color' => ['description' => 'Tag color in hex format', 'example' => '#EF4444'],
            'description' => ['description' => 'Tag description', 'example' => 'Mark threads as questions'],
        ];
    }
}
