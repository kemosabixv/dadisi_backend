<p>Dear {{ $enhancement->subscription->user->name ?? 'Member' }},</p>

<p>We attempted to renew your subscription but the payment failed after multiple retries. Please update your payment method or contact support to avoid losing access.</p>

<ul>
    <li>Subscription ID: {{ $enhancement->subscription_id ?? $enhancement->subscription->id ?? 'N/A' }}</li>
    <li>Last attempt: {{ $job->executed_at ?? now() }}</li>
    <li>Error: {{ $job->error_message ?? 'Unknown' }}</li>
</ul>

<p>Please sign in and update your payment method or contact support.</p>

<p>Thanks,<br/>Dadisi Community Labs</p>