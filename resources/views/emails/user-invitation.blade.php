<x-mail::message>
# You're Invited!

You have been invited to join the **Dadisi Community Labs** platform as a member.

To accept your invitation and set up your account, please click the button below:

<x-mail::button :url="$acceptanceUrl">
Accept Invitation
</x-mail::button>

This invitation link will expire in 72 hours.

If you did not expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
