<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam title string optional Max: 255
 * @bodyParam reason string optional Max: 1000
 * @bodyParam starts_at datetime optional
 * @bodyParam ends_at datetime optional After starts_at
 */
class UpdateLabMaintenanceBlockRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date', 'after:starts_at'],
        ];
    }

    public function bodyParameters()
    {
        return [
            'title' => ['description' => 'Maintenance title', 'example' => 'System upgrade'],
            'reason' => ['description' => 'Reason for maintenance', 'example' => 'Software system upgrade'],
            'starts_at' => ['description' => 'Maintenance start datetime', 'example' => '2025-01-20 10:00:00'],
            'ends_at' => ['description' => 'Maintenance end datetime', 'example' => '2025-01-20 18:00:00'],
        ];
    }
}
