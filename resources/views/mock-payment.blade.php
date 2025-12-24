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
                    <span class="value">
                        @php
                            $planName = $plan->name;
                            if (is_string($planName) && Str::startsWith($planName, '{')) {
                                $decoded = json_decode($planName, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $planName = $decoded[app()->getLocale()] ?? $decoded['en'] ?? reset($decoded);
                                }
                            } elseif (is_array($planName)) {
                                $planName = $planName[app()->getLocale()] ?? $planName['en'] ?? reset($planName);
                            }
                        @endphp
                        {{ $planName ?? 'N/A' }}
                    </span>
                </div>
            @endif
            @if($event ?? null)
                <div class="detail-row">
                    <span class="label">Event:</span>
                    <span class="value">
                        @php
                            $eventTitle = $event->title;
                            if (is_string($eventTitle) && Str::startsWith($eventTitle, '{')) {
                                $decoded = json_decode($eventTitle, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $eventTitle = $decoded[app()->getLocale()] ?? $decoded['en'] ?? reset($decoded);
                                }
                            } elseif (is_array($eventTitle)) {
                                $eventTitle = $eventTitle[app()->getLocale()] ?? $eventTitle['en'] ?? reset($eventTitle);
                            }
                        @endphp
                        {{ $eventTitle ?? 'N/A' }}
                    </span>
                </div>
            @endif
            @if($eventOrder ?? null)
                <div class="detail-row">
                    <span class="label">Tickets:</span>
                    <span class="value">{{ $eventOrder->quantity }} x {{ $eventOrder->currency }}
                        {{ number_format($eventOrder->unit_price, 2) }}</span>
                </div>
                @if($eventOrder->guest_name)
                    <div class="detail-row">
                        <span class="label">Attendee:</span>
                        <span class="value">{{ $eventOrder->guest_name }} ({{ $eventOrder->guest_email }})</span>
                    </div>
                @endif
                @if($eventOrder->promo_discount_amount > 0 || $eventOrder->subscriber_discount_amount > 0)
                    <div class="detail-row">
                        <span class="label">Discounts:</span>
                        <span class="value" style="color: #28a745;">
                            @if($eventOrder->promo_discount_amount > 0)
                                Promo: -{{ $eventOrder->currency }} {{ number_format($eventOrder->promo_discount_amount, 2) }}
                            @endif
                            @if($eventOrder->subscriber_discount_amount > 0)
                                Subscriber: -{{ $eventOrder->currency }}
                                {{ number_format($eventOrder->subscriber_discount_amount, 2) }}
                            @endif
                        </span>
                    </div>
                @endif
            @endif
            @if($donation ?? null)
                <div class="detail-row">
                    <span class="label">Donor:</span>
                    <span class="value">{{ $donation->donor_name ?? 'Anonymous' }}</span>
                </div>
            @endif
            <div class="detail-row">
                <span class="label">Description:</span>
                <span class="value">
                    @php
                        $description = $payment->meta['description'] ?? ($plan->name ?? 'Payment');
                        if (is_string($description) && Str::startsWith($description, '{')) {
                            $decoded = json_decode($description, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $description = $decoded[app()->getLocale()] ?? $decoded['en'] ?? reset($decoded);
                            }
                        } elseif (is_array($description)) {
                            $description = $description[app()->getLocale()] ?? $description['en'] ?? reset($description);
                        }
                    @endphp
                    {{ $description }}
                </span>
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

                <div style="margin-bottom: 25px;">
                    <p style="font-weight: bold; margin-bottom: 15px;">Select Payment Method:</p>

                    <div style="display: flex; gap: 15px;">
                        <!-- M-Pesa Option -->
                        <label style="flex: 1; cursor: pointer;">
                            <input type="radio" name="payment_method" value="mpesa" checked style="display: none;"
                                onchange="updateSelection(this)">
                            <div class="payment-option selected">
                                <div style="font-size: 32px; margin-bottom: 8px;">📱</div>
                                <div class="option-title" style="font-weight: bold;">M-Pesa</div>
                                <div style="font-size: 12px; color: #666; margin-top: 5px;">Mobile Money</div>
                            </div>
                        </label>

                        <!-- Card Option -->
                        <label style="flex: 1; cursor: pointer;">
                            <input type="radio" name="payment_method" value="card" style="display: none;"
                                onchange="updateSelection(this)">
                            <div class="payment-option">
                                <div style="font-size: 32px; margin-bottom: 8px;">💳</div>
                                <div class="option-title" style="font-weight: bold;">Card</div>
                                <div style="font-size: 12px; color: #666; margin-top: 5px;">Visa / Mastercard</div>
                            </div>
                        </label>
                    </div>
                </div>

                <style>
                    .payment-option {
                        border: 2px solid #ddd;
                        border-radius: 8px;
                        padding: 20px;
                        text-align: center;
                        transition: all 0.2s;
                        background: #fff;
                    }

                    .payment-option .option-title {
                        color: #333;
                    }

                    .payment-option:hover {
                        border-color: #28a745 !important;
                        transform: translateY(-2px);
                        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                    }

                    .payment-option.selected {
                        border-color: #28a745 !important;
                        background: #f8fff8 !important;
                    }

                    .payment-option.selected .option-title {
                        color: #28a745 !important;
                    }
                </style>

                <script>
                    function updateSelection(input) {
                        // Remove selected class from all options in the form
                        input.closest('form').querySelectorAll('.payment-option').forEach(e => {
                            e.classList.remove('selected');
                        });
                        // Add selected class to the div next to the radio input
                        input.nextElementSibling.classList.add('selected');
                    }
                </script>

                <button type="submit" class="btn">✓ Complete Payment Successfully</button>
            </form>

            <form action="{{ route('mock-payment.cancel', $payment->external_reference ?? $payment->id) }}" method="POST"
                style="margin-top: 15px;">
                @csrf
                <button type="submit" class="btn btn-secondary" style="background-color: #6c757d; width: 100%;">✗ Cancel
                    Payment</button>
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