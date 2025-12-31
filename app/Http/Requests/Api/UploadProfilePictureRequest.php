<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadProfilePictureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5120',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'image' => [
                'description' => 'Profile picture image file (JPEG, PNG, GIF, SVG, max 5MB)',
                'example' => null,
            ],
        ];
    }
}
