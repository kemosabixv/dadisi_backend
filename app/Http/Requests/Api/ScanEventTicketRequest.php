<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ScanEventTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add RBAC if needed
    }

    public function rules(): array
    {
        return [
            'qr_token' => 'required|string',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'qr_token' => ['description' => 'QR token from ticket', 'example' => 'evt_abc123def456'],
        ];
    }
}
