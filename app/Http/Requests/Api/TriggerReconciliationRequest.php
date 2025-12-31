<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TriggerReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dry_run' => 'boolean',
            'sync' => 'boolean',
            'period_start' => 'nullable|date_format:Y-m-d',
            'period_end' => 'nullable|date_format:Y-m-d',
            'county' => 'nullable|string|max:255',
            'amount_percentage_tolerance' => 'nullable|numeric|min:0|max:1',
            'amount_absolute_tolerance' => 'nullable|numeric|min:0',
            'date_tolerance' => 'nullable|integer|min:0',
            'fuzzy_match_threshold' => 'nullable|integer|min:0|max:100',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'dry_run' => ['description' => 'Preview reconciliation without making changes', 'example' => true],
            'sync' => ['description' => 'Run synchronously instead of as background job', 'example' => false],
            'period_start' => ['description' => 'Start date for reconciliation period (Y-m-d)', 'example' => '2025-01-01'],
            'period_end' => ['description' => 'End date for reconciliation period (Y-m-d)', 'example' => '2025-01-31'],
            'county' => ['description' => 'Filter by county name', 'example' => 'Nairobi'],
            'amount_percentage_tolerance' => ['description' => 'Amount tolerance as percentage (0-1)', 'example' => 0.01],
            'amount_absolute_tolerance' => ['description' => 'Absolute amount tolerance in currency', 'example' => 10.00],
            'date_tolerance' => ['description' => 'Date tolerance in days', 'example' => 1],
            'fuzzy_match_threshold' => ['description' => 'Fuzzy matching threshold (0-100)', 'example' => 80],
        ];
    }
}
