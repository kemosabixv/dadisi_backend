<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreMediaRequest
 *
 * Validates media file upload requests.
 *
 * @bodyParam file file required The file to upload (image, audio, video, pdf, gif).
 * @bodyParam attached_to string optional Context tag for the file (e.g., 'profile_header', 'post_image').
 * @bodyParam attached_to_id integer optional ID of the related resource.
 * @bodyParam temporary boolean optional Mark upload as temporary for auto-cleanup.
 */
class StoreMediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
            'attached_to' => ['nullable', 'string', 'max:50'],
            'attached_to_id' => ['nullable', 'integer'],
            'temporary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A file is required for upload.',
            'file.file' => 'The uploaded content must be a valid file.',
            'attached_to.max' => 'The context tag cannot exceed 50 characters.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'file' => ['description' => 'The file to upload (image, audio, video, pdf, gif)', 'example' => null],
            'attached_to' => ['description' => 'Context tag for the file', 'example' => 'post_image'],
            'attached_to_id' => ['description' => 'ID of the related resource', 'example' => 1],
            'temporary' => ['description' => 'Mark upload as temporary for auto-cleanup', 'example' => false],
        ];
    }
}
