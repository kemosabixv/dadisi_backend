<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreForumThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:forum_categories,id',
            'county_id' => 'nullable|exists:counties,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:10',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'category_id' => ['description' => 'Forum category ID', 'example' => 1],
            'county_id' => ['description' => 'County ID (optional)', 'example' => 35],
            'title' => ['description' => 'Thread title', 'example' => 'How to get started with Dadisi Labs?'],
            'content' => ['description' => 'Thread content (min 10 chars)', 'example' => 'I would like to learn more about using the lab facilities.'],
        ];
    }
}
