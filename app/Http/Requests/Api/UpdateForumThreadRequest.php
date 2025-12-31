<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateForumThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'is_pinned' => 'sometimes|boolean',
            'is_locked' => 'sometimes|boolean',
            'county_id' => 'sometimes|nullable|exists:counties,id',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Thread title', 'example' => 'Updated thread title'],
            'is_pinned' => ['description' => 'Pin thread to top of category', 'example' => false],
            'is_locked' => ['description' => 'Lock thread to prevent new replies', 'example' => false],
            'county_id' => ['description' => 'County ID for regional threads', 'example' => 35],
        ];
    }
}
