<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelSubscriptionPaymentRequest extends FormRequest
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
            'subscription_id' => ['required', 'integer', 'exists:plan_subscriptions,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get the body parameters for the documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'subscription_id' => [
                'description' => 'The ID of the subscription payment to cancel',
                'example' => 1,
            ],
            'reason' => [
                'description' => 'Reason for cancelling the payment',
                'example' => 'User requested cancellation due to incorrect plan selection.',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'subscription_id.required' => 'Subscription ID is required.',
            'subscription_id.integer' => 'Subscription ID must be an integer.',
            'subscription_id.exists' => 'The specified subscription does not exist.',
            'reason.string' => 'Cancellation reason must be a string.',
            'reason.max' => 'Cancellation reason cannot exceed 500 characters.',
        ];
    }
}
