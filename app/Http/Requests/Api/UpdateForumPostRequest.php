<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam content string required Min: 3 characters
 */
class UpdateForumPostRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'content' => 'required|string|min:3',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'content' => ['description' => 'Updated post content (min 3 chars)', 'example' => 'Updated comment with additional information.'],
        ];
    }
}
