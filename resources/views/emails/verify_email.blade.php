@component('mail::message')
# Hello {{ $user->name }}

Please verify your email address to complete your Dadisi account setup.

@component('mail::button', ['url' => $verifyUrl, 'color' => 'primary'])
Verify Email Address
@endcomponent

If the button above doesn't work, you can:

1. Copy and paste this verification code:
**{{ $code }}**

2. Or visit this link and enter the code:
{{ $baseUrl }}

This verification code will expire in 24 hours.

Thanks,<br>
{{ config('app.name') }}

<small>If you did not create an account, no further action is required.</small>
@endcomponent
