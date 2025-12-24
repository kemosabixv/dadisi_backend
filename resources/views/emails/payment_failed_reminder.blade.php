<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .footer {
            margin-top: 30px;
            font-size: 0.8em;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }

        .details {
            background: #fff5f5;
            padding: 15px;
            border: 1px solid #feb2b2;
            border-radius: 5px;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #e53e3e;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2 style="color: #c53030;">Payment Failed</h2>
        </div>

        <p>Dear {{ $payment->payable->user->name ?? $payment->payable->subscriber->name ?? 'Member' }},</p>

        <p>We encountered an issue while processing your payment. Unfortunately, it could not be completed at this time.
        </p>

        <div class="details">
            <p><strong>Transaction Details:</strong></p>
            <ul>
                <li>Reference: {{ $payment->order_reference }}</li>
                <li>Amount: {{ $payment->currency }} {{ number_format($payment->amount, 2) }}</li>
                @if($reason)
                    <li>Reason: {{ $reason }}</li>
                @endif
            </ul>
        </div>

        <p>Please click the button below to resolve this in your dashboard or try again.</p>

        <a href="{{ config('app.frontend_url') }}/dashboard" class="button">Go to Dashboard</a>

        <p>If you keep experiencing this issue, please contact your bank or reach out to our support team.</p>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>

</html>