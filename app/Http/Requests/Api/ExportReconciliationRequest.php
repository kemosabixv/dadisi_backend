<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ExportReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'run_id' => 'required|exists:reconciliation_runs,id',
            'status' => 'nullable|in:matched,unmatched_app,unmatched_gateway,amount_mismatch',
        ];
    }

    public function queryParameters(): array
    {
        return [
            'run_id' => ['description' => 'Reconciliation run ID to export', 'example' => 1],
            'status' => ['description' => 'Filter by status: matched, unmatched_app, unmatched_gateway, amount_mismatch', 'example' => 'matched'],
        ];
    }
}
