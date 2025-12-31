<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam content string required Min: 3 characters
 */
class StoreForumPostRequest extends FormRequest
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
            'content' => ['description' => 'Post content (min 3 chars)', 'example' => 'Great discussion! I completely agree with the points made above.'],
        ];
    }
}
