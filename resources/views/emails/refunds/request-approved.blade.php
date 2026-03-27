@component('mail::message')
# Refund Request Approved

Hello {{ $user->username ?? $user->name ?? 'there' }},

Good news! Your refund request for **{{ $amountStr }}** has been approved.

The refund is now being processed and will be credited to your original payment method shortly.

**Refund Details:**
- **Amount:** {{ $amountStr }}
- **Reference:** #{{ $refund->id }}
@if($refund->admin_notes)
- **Admin Notes:** {{ $refund->admin_notes }}
@endif

Thank you for your patience.

@component('mail::button', ['url' => $trackingUrl])
View Status
@endcomponent

Regards,<br>
{{ config('app.name') }}
@endcomponent
