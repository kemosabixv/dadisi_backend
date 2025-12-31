<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam lab_space_id integer required Must exist in lab_spaces
 * @bodyParam title string required Max: 255
 * @bodyParam reason string optional Max: 1000
 * @bodyParam starts_at datetime required After or equal to now
 * @bodyParam ends_at datetime required After starts_at
 */
class CreateLabMaintenanceBlockRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'lab_space_id' => ['required', 'exists:lab_spaces,id'],
            'title' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['required', 'date', 'after_or_equal:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    public function bodyParameters()
    {
        return [
            'lab_space_id' => ['description' => 'Lab space ID', 'example' => 1],
            'title' => ['description' => 'Maintenance title', 'example' => 'Equipment calibration'],
            'reason' => ['description' => 'Reason for maintenance', 'example' => 'Annual equipment calibration'],
            'starts_at' => ['description' => 'Maintenance start datetime', 'example' => '2025-01-15 09:00:00'],
            'ends_at' => ['description' => 'Maintenance end datetime', 'example' => '2025-01-15 17:00:00'],
        ];
    }
}
