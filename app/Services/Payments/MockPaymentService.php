<?php

namespace App\Services\Payments;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentSuccessMail;
use App\Mail\PaymentFailedReminderMail;

/**
 * Mock Payment Service
 *
 * Simulates Pesapal payment gateway for development and testing.
 * In production, this would integrate with real Pesapal API.
 */
class MockPaymentService
{
    private const SUCCESS_PHONE_PATTERNS = [
        '254701234567', // Success pattern
        '254702000000', // Success range
    ];

    private const FAILURE_PHONE_PATTERNS = [
        '254709999999', // Explicit failure
        '254708888888', // Network error
    ];

    private const PENDING_PHONE_PATTERNS = [
        '254707777777', // Pending/timeout
    ];

    /**
     * Initiate a mock payment
     *
     * @param array $paymentData Payment details
     * @return array Payment initiation response
     */
    public static function initiatePayment(array $paymentData): array
    {
        Log::info('Mock payment initiated', $paymentData);

        // Generate a payment ID in the format expected by the web route
        // Format: MOCK-{TYPE}-{TIMESTAMP}-{RANDOM}
        $type = 'SUB'; // Subscription payment
        if (isset($paymentData['type'])) {
            $type = strtoupper(substr($paymentData['type'], 0, 3));
        }
        $paymentId = 'MOCK-' . $type . '-' . time() . '-' . strtoupper(Str::random(5));
        
        // Transaction ID for tracking
        $transactionId = 'TXN_' . Str::random(12);
        $orderTrackingId = 'ORDER_' . Str::random(16);

        // Prepare payment data for storage
        $storageData = [
            'transaction_id' => $transactionId,
            'order_id' => $paymentData['order_id'] ?? null,
            'amount' => $paymentData['amount'] ?? 0,
            'currency' => $paymentData['currency'] ?? 'KES',
            'user_id' => $paymentData['user_id'] ?? null,
            'plan_id' => $paymentData['plan_id'] ?? null,
            'billing_period' => $paymentData['billing_period'] ?? 'month',
            'gateway' => 'mock_pesapal',
            'created_at' => now()->toIso8601String(),
        ];

        // Store in cache (for backward compatibility and quick lookups)
        $cacheKey = 'mock_payment_' . $paymentId;
        cache()->put($cacheKey, $storageData, now()->addHours(1));

        // Also persist to database for reliability (if user_id is available)
        if (!empty($paymentData['user_id'])) {
            try {
                \App\Models\PendingPayment::create([
                    'payment_id' => $paymentId,
                    'transaction_id' => $transactionId,
                    'user_id' => $paymentData['user_id'],
                    'subscription_id' => $paymentData['order_id'] ?? null,
                    'plan_id' => $paymentData['plan_id'] ?? null,
                    'amount' => $paymentData['amount'] ?? 0,
                    'currency' => $paymentData['currency'] ?? 'KES',
                    'status' => 'pending',
                    'gateway' => 'mock_pesapal',
                    'billing_period' => $paymentData['billing_period'] ?? 'month',
                    'metadata' => $paymentData,
                    'expires_at' => now()->addHours(1),
                ]);
            } catch (\Exception $e) {
                // Log but don't fail - cache is backup
                Log::warning('Failed to persist pending payment to database', [
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Build redirect URL using the web route
        $redirectUrl = url('/mock-payment/' . $paymentId);

        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'order_tracking_id' => $orderTrackingId,
            'redirect_url' => $redirectUrl,
            'payment_id' => $paymentId,
            'message' => 'Mock payment initialized successfully',
            'gateway' => 'mock_pesapal',
        ];
    }

    /**
     * Process a mock payment based on phone number pattern
     *
     * @param string $phone Phone number to simulate different outcomes
     * @param array $transactionData Transaction details
     * @return array Payment processing result
     */
    public static function processPayment(string $phone, array $transactionData): array
    {
        Log::info('Mock payment processing', [
            'phone' => $phone,
            'transaction_id' => $transactionData['transaction_id'] ?? null,
        ]);

        // Determine outcome based on phone pattern
        // Check explicit failure patterns first so they take precedence
        if (self::isFailurePhone($phone)) {
            return self::createFailureResponse($transactionData, 'Card declined');
        } elseif (self::isPendingPhone($phone)) {
            return self::createPendingResponse($transactionData);
        } elseif (self::isSuccessPhone($phone)) {
            return self::createSuccessResponse($transactionData);
        }

        // Default: random success (70% success rate for default numbers)
        return rand(1, 100) <= 70
            ? self::createSuccessResponse($transactionData)
            : self::createFailureResponse($transactionData, 'Generic payment failure');
    }

    /**
     * Process generic/mock webhook notification
     *
     * @param array $payload Payload from handleWebhook
     * @return array Result of processing
     */
    public static function processGenericWebhook(array $payload): array
    {
        Log::info('Processing generic/mock webhook', ['payload' => $payload]);

        $transactionId = $payload['transaction_id'] ?? null;
        $orderTrackingId = $payload['order_tracking_id'] ?? null;
        $status = $payload['status'] ?? 'failed';

        // Find the payment by transaction ID or order tracking ID
        $payment = \App\Models\Payment::where('transaction_id', $transactionId)
            ->orWhere('external_reference', $transactionId)
            ->orWhere('order_reference', $orderTrackingId)
            ->first();

        if (!$payment) {
            Log::warning('Payment not found for mock webhook', [
                'order_reference' => $orderTrackingId,
            ]);
            return ['status' => 'payment_not_found'];
        }

        // Check if already processed (idempotency)
        if ($payment->status === 'paid') {
            Log::info('Payment already marked as paid, skipping mock webhook processing', [
                'payment_id' => $payment->id,
            ]);
            return ['status' => 'already_processed'];
        }

        if ($status === 'completed') {
            // Update payment status
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // Activate payable
            self::activatePayable($payment);

            return [
                'status' => 'processed',
                'payment_id' => $payment->id,
                'new_status' => 'paid'
            ];
        } else {
            $payment->update(['status' => 'failed']);
            
            // Handle failure
            self::failPayable($payment, $status);

            return [
                'status' => 'processed',
                'payment_id' => $payment->id,
                'new_status' => 'failed'
            ];
        }
    }

    /**
     * Handle failed/cancelled payable entity
     */
    protected static function failPayable(\App\Models\Payment $payment, string $status): void
    {
        try {
            if ($payment->payable_type === 'event_order' || 
                $payment->payable_type === 'App\\Models\\EventOrder') {
                
                $order = \App\Models\EventOrder::find($payment->payable_id);
                $order?->update(['status' => 'failed']);
                
            } elseif ($payment->payable_type === 'donation' ||
                      $payment->payable_type === 'App\\Models\\Donation') {
                
                $donation = \App\Models\Donation::find($payment->payable_id);
                $donation?->update(['status' => 'failed']);
            } else {
                // Subscription payments
                $payable = $payment->payable;
                if ($payable) {
                    $enhancement = \App\Models\SubscriptionEnhancement::where('subscription_id', $payable->id)->first();
                    if ($enhancement) {
                        $metadata = $enhancement->metadata ?? [];
                        if (is_string($metadata)) {
                            $metadata = json_decode($metadata, true);
                        }
                        
                        $previousId = $metadata['previous_subscription_id'] ?? null;
                        
                        // Revert user's active subscription
                        $user = $payable->subscriber ?? $payable->user;
                        if ($user && $user instanceof \App\Models\User) {
                            $user->update(['active_subscription_id' => $previousId]);
                            Log::info('User subscription reverted after failed payment', [
                                'user_id' => $user->id,
                                'previous_id' => $previousId,
                                'failed_id' => $payable->id
                            ]);
                        }
                        
                        // Mark the failed subscription itself as cancelled
                        $payable->update(['status' => 'cancelled']);

                        $enhancement->update([
                            'status' => $status === 'cancelled' ? 'cancelled' : 'payment_failed',
                            'last_renewal_result' => 'failed',
                            'last_renewal_error' => 'Webhook reported: ' . $status,
                        ]);
                    }
                }
            }
            
            \App\Models\AuditLog::log('payment.failed', $payment, null, ['status' => $status], 'Payment failed/cancelled via mock webhook');
            
            // Trigger failure email
            try {
                $user = $payment->payable->user ?? $payment->payable->subscriber ?? null;
                if ($user && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($user->email)->queue(new PaymentFailedReminderMail($payment, 'Mock webhook reported: ' . $status));
                }
            } catch (\Exception $e) {
                Log::error('MockPaymentService: Failed to send failure email', ['error' => $e->getMessage()]);
            }
            
        } catch (\Exception $e) {
            Log::error('MockPaymentService: Failed to handle failed payable', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Activate the payable entity after successful payment
     * (Mirrors PesapalService logic for consistency)
     */
    protected static function activatePayable(\App\Models\Payment $payment): void
    {
        try {
            if ($payment->payable_type === 'event_order' || 
                $payment->payable_type === 'App\\Models\\EventOrder') {
                
                $order = \App\Models\EventOrder::find($payment->payable_id);
                if ($order) {
                    $order->update(['status' => 'paid', 'purchased_at' => now()]);
                    if ($order->promo_code_id) {
                        \App\Models\PromoCode::where('id', $order->promo_code_id)->increment('times_used');
                    }
                    \App\Models\AuditLog::log('payment.completed', $payment, null, ['status' => 'paid'], 'Event order activated');
                }
            } elseif ($payment->payable_type === 'donation' ||
                      $payment->payable_type === 'App\\Models\\Donation') {
                
                $donation = \App\Models\Donation::find($payment->payable_id);
                if ($donation) {
                    $donation->update(['status' => 'paid', 'payment_date' => now()]);
                    \App\Models\AuditLog::log('payment.completed', $payment, null, ['status' => 'paid'], 'Donation activated');
                }
            } else {
                // Subscription payments
                $payable = $payment->payable;
                if ($payable) {
                    if (method_exists($payable, 'activate')) {
                        $payable->activate();
                    } else {
                        $payable->update(['status' => 'active']);
                    }

                    if (method_exists($payable, 'user') && $payable->user) {
                        $payable->user->update([
                            'subscription_status' => 'active',
                            'subscription_activated_at' => now(),
                            'last_payment_date' => now(),
                        ]);
                    }
                    \App\Models\AuditLog::log('payment.completed', $payment, null, ['status' => 'paid'], 'Subscription activated');
                }
            }

            // Trigger success email
            try {
                $user = $payment->payable->user ?? $payment->payable->subscriber ?? null;
                if ($user && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($user->email)->queue(new PaymentSuccessMail($payment));
                }
            } catch (\Exception $e) {
                Log::error('MockPaymentService: Failed to send success email', ['error' => $e->getMessage()]);
            }

        } catch (\Exception $e) {
            Log::error('MockPaymentService: Failed to activate payable', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Check if phone number should result in success
     */
    private static function isSuccessPhone(string $phone): bool
    {
        return in_array($phone, self::SUCCESS_PHONE_PATTERNS) ||
               preg_match('/^2547(0|1|2|3|4|5)/', $phone);
    }

    /**
     * Check if phone number should result in failure
     */
    private static function isFailurePhone(string $phone): bool
    {
        return in_array($phone, self::FAILURE_PHONE_PATTERNS);
    }

    /**
     * Check if phone number should result in pending
     */
    private static function isPendingPhone(string $phone): bool
    {
        return in_array($phone, self::PENDING_PHONE_PATTERNS);
    }

    /**
     * Create success response
     */
    private static function createSuccessResponse(array $transactionData): array
    {
        return [
            'success' => true,
            'status' => 'completed',
            'transaction_id' => $transactionData['transaction_id'] ?? 'MOCK_' . Str::random(12),
            'order_id' => $transactionData['order_id'] ?? null,
            'amount' => $transactionData['amount'] ?? 0,
            'currency' => 'KES',
            'message' => 'Payment completed successfully',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Create failure response
     */
    private static function createFailureResponse(array $transactionData, string $reason): array
    {
        return [
            'success' => false,
            'status' => 'failed',
            'transaction_id' => $transactionData['transaction_id'] ?? 'MOCK_' . Str::random(12),
            'order_id' => $transactionData['order_id'] ?? null,
            'error_code' => 'PAYMENT_FAILED',
            'error_message' => $reason,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Create pending response
     */
    private static function createPendingResponse(array $transactionData): array
    {
        return [
            'success' => false,
            'status' => 'pending',
            'transaction_id' => $transactionData['transaction_id'] ?? 'MOCK_' . Str::random(12),
            'order_id' => $transactionData['order_id'] ?? null,
            'message' => 'Payment is pending. Please try again later.',
            'retry_after' => 30,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Query payment status for a mock transaction.
     *
     * @param string $transactionId
     * @return array
     */
    public static function queryPaymentStatus(string $transactionId): array
    {
        // Check cache first
        $cacheKey = 'mock_payment_' . $transactionId;
        $cached = cache()->get($cacheKey);
        if ($cached) {
            return [
                'transaction_id' => $transactionId,
                'status' => 'pending',
                'amount' => $cached['amount'] ?? 0,
                'currency' => $cached['currency'] ?? 'KES',
            ];
        }

        // Try to find a Payment record
        try {
            $payment = \App\Models\Payment::where('transaction_id', $transactionId)
                ->orWhere('external_reference', $transactionId)
                ->orWhere('order_reference', $transactionId)
                ->orWhere('id', $transactionId)
                ->first();

            if (!$payment) {
                return [
                    'transaction_id' => $transactionId,
                    'status' => 'not_found',
                ];
            }

            return [
                'transaction_id' => $transactionId,
                'status' => $payment->status ?? 'unknown',
                'amount' => $payment->amount ?? 0,
                'currency' => $payment->currency ?? 'KES',
                'meta' => $payment->meta ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('MockPaymentService: queryPaymentStatus error', ['error' => $e->getMessage(), 'transaction_id' => $transactionId]);
            return [
                'transaction_id' => $transactionId,
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process a mock refund for a transaction.
     *
     * @param string $transactionId
     * @param float|null $amount
     * @return array
     * @throws \Exception
     */
    public static function refundPayment(string $transactionId, ?float $amount = null): array
    {
        try {
            $payment = \App\Models\Payment::where('transaction_id', $transactionId)
                ->orWhere('external_reference', $transactionId)
                ->orWhere('order_reference', $transactionId)
                ->orWhere('id', $transactionId)
                ->first();

            if (!$payment) {
                throw new \Exception('Payment not found for refund');
            }

            $refundAmount = $amount ?? ($payment->amount ?? 0);

            // Update payment record with refund metadata
            $meta = $payment->meta ?? [];
            $meta['refunded_amount'] = ($meta['refunded_amount'] ?? 0) + $refundAmount;
            $meta['last_refund_at'] = now()->toIso8601String();

            $payment->update([
                'status' => 'refunded',
                'meta' => $meta,
            ]);

            // Optionally create a Refund model if it exists
            if (class_exists('\App\Models\Refund')) {
                try {
                    \App\Models\Refund::create([
                        'payment_id' => $payment->id,
                        'amount' => $refundAmount,
                        'reference' => 'REF_' . strtoupper(substr(md5(uniqid()), 0, 8)),
                        'metadata' => ['reason' => 'mock_refund'],
                    ]);
                } catch (\Exception $e) {
                    Log::warning('MockPaymentService: failed to create Refund model', ['error' => $e->getMessage()]);
                }
            }

            return [
                'refund_id' => 'REF_' . strtoupper(substr(md5(uniqid()), 0, 8)),
                'status' => 'completed',
                'amount' => $refundAmount,
            ];

        } catch (\Exception $e) {
            Log::error('MockPaymentService: refundPayment error', ['error' => $e->getMessage(), 'transaction_id' => $transactionId]);
            throw $e;
        }
    }
}
