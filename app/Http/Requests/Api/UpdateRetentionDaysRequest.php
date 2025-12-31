<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRetentionDaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data_type' => 'required|string|in:orphaned_media,temporary_media,user_accounts,audit_logs,session_data,failed_jobs,temp_files,backups,pending_payments,webhook_events',
            'retention_days' => 'nullable|integer|min:0|max:3650',
            'retention_minutes' => 'nullable|integer|min:1|max:525600',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'data_type' => [
                'description' => 'Type of data to set retention for',
                'example' => 'audit_logs',
            ],
            'retention_days' => [
                'description' => 'Number of days to retain data',
                'example' => 90,
            ],
            'retention_minutes' => [
                'description' => 'Number of minutes to retain data',
                'example' => 5,
            ],
        ];
    }
}
