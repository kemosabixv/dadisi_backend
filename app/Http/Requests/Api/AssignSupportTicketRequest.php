<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AssignSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'assigned_to' => 'required|exists:users,id',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'assigned_to' => ['description' => 'User ID of the agent to assign the ticket to', 'example' => 2],
        ];
    }
}
