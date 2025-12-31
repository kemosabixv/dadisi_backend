<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserDataRetentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'retention_days' => 'nullable|integer|min:0|max:3650',
            'retention_minutes' => 'nullable|integer|min:1|max:525600',
            'auto_delete' => 'boolean',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'retention_days' => [
                'description' => 'Number of days to retain user data',
                'example' => 365,
            ],
            'retention_minutes' => [
                'description' => 'Number of minutes to retain user data',
                'example' => 10,
            ],
            'auto_delete' => [
                'description' => 'Automatically delete data after retention period',
                'example' => true,
            ],
            'description' => [
                'description' => 'Description of the retention policy',
                'example' => 'Delete inactive accounts after 1 year',
            ],
        ];
    }
}
