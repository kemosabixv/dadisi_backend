@component('mail::message')
# Hello {{ $notifiable->name }}

You are receiving this email because we received a password reset request for your Dadisi account.

@component('mail::button', ['url' => $url, 'color' => 'primary'])
Reset Password
@endcomponent

This password reset link will expire in {{ config('auth.passwords.users.expire', 60) }} minutes.

If you did not request a password reset, no further action is required.

Thanks,<br>
{{ config('app.name') }}

<small>If you did not create an account, no further action is required.</small>
@endcomponent
