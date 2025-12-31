<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AddSupportTicketResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string',
            'attachments' => 'nullable|array',
            'is_internal' => 'boolean',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'message' => ['description' => 'Response message content', 'example' => 'Thank you for contacting us. We have resolved your issue.'],
            'attachments' => ['description' => 'Array of attachment file paths', 'example' => []],
            'is_internal' => ['description' => 'Whether this is an internal note (not visible to user)', 'example' => false],
        ];
    }
}
