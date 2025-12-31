<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'nullable|string|in:low,medium,high',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'subject' => ['description' => 'Ticket subject', 'example' => 'Issue with membership payment'],
            'description' => ['description' => 'Detailed description of the issue', 'example' => 'My payment was deducted but my subscription was not activated.'],
            'priority' => ['description' => 'Ticket priority level', 'example' => 'high'],
        ];
    }
}
