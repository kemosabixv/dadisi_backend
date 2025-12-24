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
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
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
            <h2>Payment Successful</h2>
        </div>

        <p>Dear {{ $payment->payable->user->name ?? $payment->payable->subscriber->name ?? 'Member' }},</p>

        <p>Thank you for your payment. Your transaction has been processed successfully.</p>

        <div class="details">
            <p><strong>Transaction Info:</strong></p>
            <ul>
                <li>Amount: {{ $payment->currency }} {{ number_format($payment->amount, 2) }}</li>
                <li>Reference: {{ $payment->order_reference }}</li>
                <li>Date: {{ $payment->paid_at->format('M d, Y H:i') }}</li>
                <li>Status: Paid</li>
            </ul>
        </div>

        @if(str_contains($payment->payable_type, 'EventOrder'))
            <p>Your registration for the event is now confirmed. You can view your ticket in your dashboard.</p>
        @elseif(str_contains($payment->payable_type, 'Donation'))
            <p>Your generous contribution helps us continue our mission. A formal receipt will be available in your giving
                history.</p>
        @elseif(str_contains($payment->payable_type, 'Subscription'))
            <p>Your subscription is now active! You have full access to all features included in your plan.</p>
        @endif

        <a href="{{ config('app.frontend_url') }}/dashboard" class="button">Go to Dashboard</a>

        <div class="footer">
            <p>If you have any questions, please contact our support team.</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>

</html>