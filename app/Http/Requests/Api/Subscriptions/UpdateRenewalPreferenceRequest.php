<?php

namespace App\Http\Requests\Api\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRenewalPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'renewal_type' => 'nullable|in:automatic,manual',
            'send_renewal_reminders' => 'nullable|boolean',
            'reminder_days_before' => 'nullable|integer|min:1|max:30',
            'preferred_payment_method' => 'nullable|string|max:50',
            'auto_switch_to_free_on_expiry' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'renewal_type.in' => 'Renewal type must be automatic or manual',
            'reminder_days_before.min' => 'Reminder must be at least 1 day before expiry',
            'reminder_days_before.max' => 'Reminder cannot be more than 30 days before expiry',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'renewal_type' => [
                'description' => 'Renewal type: automatic or manual',
                'example' => 'automatic',
            ],
            'send_renewal_reminders' => [
                'description' => 'Whether to send renewal reminder emails',
                'example' => true,
            ],
            'reminder_days_before' => [
                'description' => 'Number of days before expiry to send reminder',
                'example' => 7,
            ],
            'preferred_payment_method' => [
                'description' => 'Preferred payment method for renewal',
                'example' => 'mobile_money',
            ],
            'auto_switch_to_free_on_expiry' => [
                'description' => 'Automatically downgrade to Free plan on expiry',
                'example' => true,
            ],
            'notes' => [
                'description' => 'Additional preference notes',
                'example' => 'Prefer payment on the first of the month',
            ],
        ];
    }
}
