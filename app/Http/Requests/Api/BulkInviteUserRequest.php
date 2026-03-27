<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class BulkInviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invitations' => 'required|array|min:1|max:50',
            'invitations.*.email' => [
                'required',
                'email',
                'unique:users,email',
                function ($attribute, $value, $fail) {
                    $exists = \App\Models\Invitation::where('email', $value)
                        ->whereNull('accepted_at')
                        ->where('expires_at', '>', now())
                        ->exists();
                    if ($exists) {
                        $fail("A pending invitation already exists for {$value}.");
                    }
                },
            ],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'invitations' => [
                'description' => 'Array of invitations to send',
                'example' => [['email' => 'user@example.com']],
            ],
        ];
    }
}
