@component('mail::message')
# Refund Request Update

Hello {{ $user->username ?? $user->name ?? 'there' }},

Your refund request for **#{{ $refund->id }}** has been reviewed.

Unfortunately, your request has been rejected.

**Reason:** {{ $reason }}

@if($refund->admin_notes)
**Additional Notes:**
{{ $refund->admin_notes }}
@endif

If you have any questions or believe this is an error, please contact our support team.

@component('mail::button', ['url' => $trackingUrl])
View Status
@endcomponent

Regards,<br>
{{ config('app.name') }}
@endcomponent
