@component('mail::message')
# Refund Request Received

Hello {{ $user->username ?? $user->name ?? 'there' }},

We have received your refund request for **{{ $amountStr }}**.

**Request Details:**
- **Amount:** {{ $amountStr }}
- **Reason:** {{ $refund->reason_display ?? $refund->reason }}
- **Date:** {{ $refund->requested_at->format('M d, Y H:i') }}

Our team will review your request shortly. You can track the status of your refund in your dashboard.

@component('mail::button', ['url' => $trackingUrl])
View Status
@endcomponent

Thank you for your patience.

Regards,<br>
{{ config('app.name') }}
@endcomponent
