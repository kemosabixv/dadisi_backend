<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add RBAC if needed
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:event_tags,slug',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Tag name', 'example' => 'Networking'],
            'slug' => ['description' => 'URL-safe slug', 'example' => 'networking'],
        ];
    }
}
