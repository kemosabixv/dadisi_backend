<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadProfilePictureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'profile_picture' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:5120'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'profile_picture.required' => 'Profile picture is required.',
            'profile_picture.image' => 'The uploaded file must be an image.',
            'profile_picture.mimes' => 'Profile picture must be a JPEG, PNG, JPG, GIF, or SVG file.',
            'profile_picture.max' => 'Profile picture cannot exceed 5 MB.',
        ];
    }

    /**
     * Get custom body parameters for Scribe documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'profile_picture' => [
                'description' => 'Profile picture image file (JPEG, PNG, GIF, SVG, max 5MB)',
                'example' => null,
            ],
        ];
    }
}
