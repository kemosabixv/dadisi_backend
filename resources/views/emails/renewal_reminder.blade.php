<html>
<body>
    <p>Hello {{ $reminder->user->name ?? 'Member' }},</p>

    <p>This is a reminder that your subscription for plan <strong>#{{ $reminder->metadata['plan_id'] ?? '' }}</strong> will expire on <strong>{{ $reminder->metadata['ends_at'] ?? '' }}</strong>.</p>

    <p>Please renew your subscription to avoid interruption. You can renew using the app or by visiting your account subscriptions page.</p>

    <p>Thank you,<br/>Dadisi Community Labs</p>
</body>
</html>
