<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupportTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:open,pending,resolved,closed',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => ['description' => 'New ticket status: open, pending, resolved, or closed', 'example' => 'resolved'],
        ];
    }
}
