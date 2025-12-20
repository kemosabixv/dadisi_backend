<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Dadisi</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }

        .success-card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .success-icon {
            font-size: 64px;
            color: #28a745;
            margin-bottom: 20px;
        }

        .success-message {
            font-size: 24px;
            color: #28a745;
            margin-bottom: 20px;
        }

        .payment-details {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 4px;
            text-align: left;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .label {
            font-weight: bold;
        }

        .value {
            text-align: right;
        }

        .btn {
            display: inline-block;
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            margin-top: 20px;
        }

        .btn:hover {
            background: #0056b3;
        }

        .dev-notice {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="success-card">
        <div class="success-icon">✓</div>
        <div class="success-message">{{ $message }}</div>

        <div class="payment-details">
            <div class="detail-row">
                <span class="label">Payment ID:</span>
                <span class="value">{{ $payment->id }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Order Reference:</span>
                <span class="value">{{ $payment->order_reference }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Amount:</span>
                <span class="value">{{ $payment->currency ?? 'KES' }} {{ number_format($payment->amount, 2) }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Status:</span>
                <span class="value" style="color: #28a745; font-weight: bold;">{{ strtoupper($payment->status) }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Paid At:</span>
                <span class="value">{{ $payment->paid_at?->format('Y-m-d H:i:s') ?? 'Just now' }}</span>
            </div>
        </div>

        <a href="{{ config('app.frontend_url', 'http://localhost:3000') }}/dashboard/subscription" class="btn">
            Go to Dashboard
        </a>

        <div class="dev-notice">
            <strong>✓ Development Environment</strong><br>
            This was a simulated payment. In production, this would be a real Pesapal transaction.
        </div>
    </div>
</body>

</html>