<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesapal Mock Payment - Local Development</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }

        .payment-card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .payment-details {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
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

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            background: #ffd700;
            color: #333;
            border-radius: 4px;
            font-size: 12px;
            text-transform: uppercase;
        }

        .status-badge.paid {
            background: #28a745;
            color: white;
        }

        .btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-bottom: 10px;
        }

        .btn:hover {
            background: #218838;
        }

        .btn.fail {
            background: #dc3545;
        }

        .btn.fail:hover {
            background: #c82333;
        }

        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .test-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #17a2b8;
            color: white;
            border-radius: 3px;
            font-size: 11px;
            margin-left: 10px;
        }
    </style>
</head>

<body>
    <div class="payment-card">
        <div class="warning">
            <strong>⚠️ Development Environment Notice</strong><br>
            This is a mock payment page for local development testing only.
            In production, this would redirect to the actual Pesapal payment gateway.
        </div>

        <h1>
            Pesapal Payment
            @if($payment->meta['test_payment'] ?? false)
                <span class="test-badge">TEST</span>
            @endif
        </h1>

        <div class="payment-details">
            @php
                $paymentType = $payment->meta['payment_type'] ?? 'test';
                $paymentTypeBadges = [
                    'test' => ['label' => '🧪 Test', 'color' => '#17a2b8'],
                    'subscription' => ['label' => '💳 Subscription', 'color' => '#28a745'],
                    'donation' => ['label' => '❤️ Donation', 'color' => '#e83e8c'],
                    'event' => ['label' => '🎫 Event Ticket', 'color' => '#fd7e14'],
                ];
                $badge = $paymentTypeBadges[$paymentType] ?? $paymentTypeBadges['test'];
            @endphp
            <div class="detail-row">
                <span class="label">Payment Type:</span>
                <span class="value">
                    <span
                        style="display: inline-block; padding: 3px 10px; background: {{ $badge['color'] }}; color: white; border-radius: 4px; font-size: 12px;">
                        {{ $badge['label'] }}
                    </span>
                </span>
            </div>
            @if($plan)
                <div class="detail-row">
                    <span class="label">Plan:</span>
                    <span class="value">{{ $plan->name ?? 'N/A' }}</span>
                </div>
            @endif
            <div class="detail-row">
                <span class="label">Description:</span>
                <span class="value">{{ $payment->meta['description'] ?? ($plan->name ?? 'Payment') }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Amount:</span>
                <span class="value">{{ $payment->currency ?? 'KES' }} {{ number_format($payment->amount, 2) }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Email:</span>
                <span class="value">{{ $payment->meta['user_email'] ?? ($subscription?->user?->email ?? 'N/A') }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Payment ID:</span>
                <span class="value">{{ $payment->id }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Tracking ID:</span>
                <span class="value">{{ $payment->external_reference }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Order Reference:</span>
                <span class="value">{{ $payment->order_reference }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Status:</span>
                <span class="value">
                    <span
                        class="status-badge {{ $payment->status === 'paid' ? 'paid' : '' }}">{{ $payment->status }}</span>
                </span>
            </div>
        </div>

        @if($payment->status !== 'paid')
            <form action="{{ route('mock-payment.complete', $payment->external_reference ?? $payment->id) }}" method="POST">
                @csrf
                <p>Click below to simulate payment completion:</p>

                <button type="submit" class="btn">✓ Complete Payment Successfully</button>
            </form>

            <p style="text-align: center; margin: 20px 0; font-size: 14px; color: #666;">Or use the API endpoint:</p>

            <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px;">
                POST {{ route('webhooks.pesapal') }}<br>
                Payload: {"OrderTrackingId": "{{ $payment->external_reference }}", "OrderNotificationType":
                "PAYMENT_RECEIVED"}
            </div>
        @else
            <div style="background: #d4edda; padding: 20px; border-radius: 4px; text-align: center; color: #155724;">
                <strong>✓ Payment Already Completed</strong><br>
                This payment has already been processed.
            </div>
        @endif

        <p style="margin-top: 20px; font-size: 12px; color: #666;">
            This mock page helps test the payment flow without connecting to Pesapal.
            In production, users would be redirected to Pesapal's secure payment page.
        </p>
    </div>
</body>

</html>