<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateForumTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tagId = $this->route('tag')?->id;

        return [
            'name' => 'sometimes|string|max:50|unique:forum_tags,name,' . $tagId,
            'color' => 'nullable|string|max:7|regex:/^#[A-Fa-f0-9]{6}$/',
            'description' => 'nullable|string|max:255',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Tag name', 'example' => 'Announcement'],
            'color' => ['description' => 'Tag color in hex format', 'example' => '#3B82F6'],
            'description' => ['description' => 'Tag description', 'example' => 'Official announcements'],
        ];
    }
}
