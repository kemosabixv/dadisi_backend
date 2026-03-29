<html>
<body>
    <p>Hello {{ $reminder->user->name ?? 'Member' }},</p>

    <p>This is a reminder that your subscription for plan <strong>#{{ $reminder->metadata['plan_id'] ?? '' }}</strong> will expire on <strong>{{ $reminder->metadata['ends_at'] ?? '' }}</strong>.</p>

    <p>Please renew your subscription to avoid interruption. You can renew using the app or by visiting your account subscriptions page.</p>

    <p><strong>Note for Recurring Payments:</strong> Pesapal will send you an email alert 1-2 days before any recurring charge. You can use the link in that email to opt-out or pause your subscription at any time.</p>

    <p>Thank you,<br/>Dadisi Community Labs</p>
</body>
</html>
