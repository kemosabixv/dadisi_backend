<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchedulerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'command_name' => 'required|string',
            'run_time' => 'required|date_format:H:i',
            'frequency' => 'in:daily,weekly,monthly,hourly',
            'enabled' => 'boolean',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'command_name' => [
                'description' => 'Name of the command to schedule',
                'example' => 'schedule:run',
            ],
            'run_time' => [
                'description' => 'Time to run the command (HH:ii format)',
                'example' => '02:00',
            ],
            'frequency' => [
                'description' => 'Frequency of execution',
                'example' => 'daily',
            ],
            'enabled' => [
                'description' => 'Whether the scheduler is enabled',
                'example' => true,
            ],
        ];
    }
}
