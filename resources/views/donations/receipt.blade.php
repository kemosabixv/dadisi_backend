<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $donation->reference }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 40px;
        }

        .receipt-box {
            max-width: 800px;
            margin: auto;
            border: 1px solid #eee;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #f9f9f9;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #000;
        }

        .receipt-title {
            font-size: 18px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .detail-item label {
            display: block;
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .detail-item span {
            font-size: 14px;
            font-weight: 500;
        }

        .amount-box {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 4px;
            text-align: center;
            margin-bottom: 30px;
        }

        .amount-value {
            font-size: 32px;
            font-weight: bold;
            color: #000;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        @media print {
            body {
                padding: 0;
            }

            .receipt-box {
                border: none;
                box-shadow: none;
            }

            .no-print {
                display: none;
            }
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #000;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="receipt-box">
        <div class="header">
            <div class="logo">DADISI COMMUNITY LABS</div>
            <div class="receipt-title">Official Receipt</div>
        </div>

        <div class="details">
            <div class="detail-item">
                <label>Receipt Number</label>
                <span>{{ $donation->receipt_number }}</span>
            </div>
            <div class="detail-item">
                <label>Date</label>
                <span>{{ $donation->payment?->paid_at ? $donation->payment->paid_at->format('M d, Y') : $donation->created_at->format('M d, Y') }}</span>
            </div>
            <div class="detail-item">
                <label>Received From</label>
                <span>{{ $donation->donor_name }}</span>
            </div>
            <div class="detail-item">
                <label>Reference</label>
                <span>{{ $donation->reference }}</span>
            </div>
            <div class="detail-item">
                <label>Payment Method</label>
                @php
                    $payment = $donation->payment;
                    $method = 'Unknown';
                    if ($payment) {
                        // Try to get method from meta first (Pesapal returns this)
                        $meta = is_string($payment->meta) ? json_decode($payment->meta, true) : $payment->meta;
                        if (!empty($meta['payment_method'])) {
                            $method = ucfirst($meta['payment_method']);
                        } elseif ($payment->method && $payment->method !== 'pending') {
                            $method = ucfirst($payment->method);
                        } elseif ($payment->paid_at) {
                            $method = 'M-Pesa'; // Default for completed payments without method
                        } else {
                            $method = 'Payment Pending';
                        }
                    }
                @endphp
                <span>{{ $method }}</span>
            </div>
            <div class="detail-item">
                <label>Campaign</label>
                <span>{{ $donation->campaign?->title ?? 'General Donation' }}</span>
            </div>
        </div>

        <div class="amount-box">
            <label style="display: block; font-size: 12px; color: #999; margin-bottom: 10px;">TOTAL DONATION</label>
            <div class="amount-value">{{ $donation->currency }} {{ number_format($donation->amount, 2) }}</div>
        </div>

        <div class="footer">
            <p>Thank you for your generous support of Dadisi Community Labs.</p>
            <p>This is a computer-generated receipt and does not require a signature.</p>
            <p>© {{ date('Y') }} Dadisi Community Labs. All Rights Reserved.</p>
        </div>

        <div class="no-print" style="text-align: center;">
            <a href="javascript:window.print()" class="btn">Print Receipt</a>
        </div>
    </div>
</body>

</html>