<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRenewalPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'renewal_type' => ['nullable', 'in:automatic,manual'],
            'send_renewal_reminders' => ['nullable', 'boolean'],
            'reminder_days_before' => ['nullable', 'integer', 'min:1', 'max:30'],
            'preferred_payment_method' => ['nullable', 'string', 'max:50'],
            'auto_switch_to_free_on_expiry' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get the body parameters for the documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'renewal_type' => [
                'description' => 'Type of renewal (automatic or manual)',
                'example' => 'automatic',
            ],
            'send_renewal_reminders' => [
                'description' => 'Whether to send renewal reminder emails',
                'example' => true,
            ],
            'reminder_days_before' => [
                'description' => 'Number of days before expiry to send reminders',
                'example' => 7,
            ],
            'preferred_payment_method' => [
                'description' => 'User preferred payment method',
                'example' => 'mpesa',
            ],
            'auto_switch_to_free_on_expiry' => [
                'description' => 'Whether to automatically downgrade to a free plan on expiry',
                'example' => true,
            ],
            'notes' => [
                'description' => 'Optional internal notes about preferences',
                'example' => 'I prefer to review manually before renewal.',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'renewal_type.in' => 'Renewal type must be either automatic or manual.',
            'send_renewal_reminders.boolean' => 'Reminder preference must be a boolean.',
            'reminder_days_before.integer' => 'Reminder days must be an integer between 1 and 30.',
            'reminder_days_before.min' => 'Reminder days must be at least 1.',
            'reminder_days_before.max' => 'Reminder days cannot exceed 30.',
            'preferred_payment_method.max' => 'Payment method cannot exceed 50 characters.',
            'auto_switch_to_free_on_expiry.boolean' => 'Auto-switch preference must be a boolean.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }
}
