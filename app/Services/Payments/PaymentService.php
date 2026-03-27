<?php

namespace App\Services\Payments;

use App\DTOs\Payments\PaymentFilterDTO;
use App\DTOs\Payments\PaymentRequestDTO;
use App\Exceptions\PaymentException;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use App\Services\Contracts\NotificationServiceContract;
use App\Services\Contracts\PaymentServiceContract;
use App\Services\PaymentGateway\GatewayManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PaymentService
 *
 * Centralized service for payment orchestration.
 * Uses GatewayManager to interact with specific payment providers.
 */
class PaymentService implements PaymentServiceContract
{
    /**
     * Create a new PaymentService instance.
     *
     * @param  GatewayManager  $gatewayManager  The payment gateway manager
     */
    public function __construct(
        protected GatewayManager $gatewayManager,
        protected NotificationServiceContract $notificationService
    ) {}

    /**
     * Get payment form metadata.
     *
     * Returns configuration data for payment forms including
     * available methods, currencies, and billing periods.
     *
     * @return array{methods: string[], currencies: string[], billing_periods: string[], active_gateway: string}
     */
    public function getPaymentFormMetadata(): array
    {
        return [
            'methods' => ['pesapal', 'mock'],
            'currencies' => ['KES', 'USD'],
            'billing_periods' => ['month', 'year'],
            'active_gateway' => GatewayManager::getActiveGateway(),
        ];
    }

    /**
     * List payments with filtering.
     *
     * @param  array  $filters  Filter criteria (status, date_from, date_to, amount_from, amount_to)
     * @param  int  $perPage  Number of results per page
     * @return LengthAwarePaginator Paginated payment results
     */
    public function listPayments(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $filterDto = PaymentFilterDTO::fromArray($filters);
        $query = Payment::query()->with('payer');

        foreach ($filterDto->toArray() as $key => $value) {
            if ($key === 'date_from') {
                $query->whereDate('created_at', '>=', $value);
            } elseif ($key === 'date_to') {
                $query->whereDate('created_at', '<=', $value);
            } elseif ($key === 'amount_from') {
                $query->where('amount', '>=', $value);
            } elseif ($key === 'amount_to') {
                $query->where('amount', '<=', $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get payment history for a user.
     *
     * @param  Authenticatable  $user  The authenticated user
     * @param  int  $perPage  Number of results per page
     * @return LengthAwarePaginator Paginated payment history
     */
    public function getPaymentHistory(Authenticatable $user, int $perPage = 15, ?string $payableType = null): LengthAwarePaginator
    {
        $query = Payment::where('payer_id', $user->getAuthIdentifier());

        if ($payableType) {
            $query->where('payable_type', $payableType);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Process/Initiate a payment.
     *
     * Creates a local payment record and initiates the payment with the gateway.
     *
     * @param  Authenticatable|null  $user  The authenticated user making the payment (null for guests)
     * @param  array  $data  Payment data (amount, currency, description, etc.)
     * @return array{payment_id: int, transaction_id: string, redirect_url: string, status: string}
     *
     * @throws \App\Exceptions\PaymentException When payment initiation fails
     */
    public function processPayment(?Authenticatable $user, array $data): array
    {
        try {
            $request = PaymentRequestDTO::fromArray($data);

            return DB::transaction(function () use ($user, $request) {
                // Normalize payable_type if it is a class name
                $payableType = $request->payable_type;
                if (class_exists($payableType)) {
                    $payableType = (new $payableType)->getMorphClass();
                }

                // 1. Create the local payment record
                $payment = Payment::create([
                    'payer_id' => $user?->getAuthIdentifier(),
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'gateway' => GatewayManager::getActiveGateway(),
                    'method' => $request->payment_method,
                    'description' => $request->description,
                    'reference' => $request->reference ?? ('REF_'.uniqid()),
                    'order_reference' => 'ORDER_'.strtoupper(uniqid()),
                    'county' => $request->county,
                    'status' => 'pending',
                    'payable_type' => $payableType,
                    'payable_id' => $request->payable_id,
                    'metadata' => $request->metadata,
                ]);

                // 2. Initiate with gateway
                $result = $this->gatewayManager->initiatePayment($request, $payment);

                if (! $result->success) {
                    throw PaymentException::processingFailed($result->message);
                }

                // 3. Update payment with gateway details
                $payment->update([
                    'transaction_id' => $result->transactionId,
                    'external_reference' => $result->merchantReference,
                    'pesapal_order_id' => $result->transactionId, // For compatibility
                ]);

                AuditLog::create([
                    'actor_id' => $user?->getAuthIdentifier() ?? 0,
                    'action' => 'payment_initiated',
                    'model' => Payment::class,
                    'model_id' => $payment->id,
                    'changes' => ['transaction_id' => $result->transactionId],
                ]);

                return [
                    'payment_id' => $payment->id,
                    'transaction_id' => $result->transactionId,
                    'redirect_url' => $result->redirectUrl,
                    'status' => 'pending',
                ];
            });
        } catch (\Exception $e) {
            Log::error('Payment initiation failed', ['error' => $e->getMessage()]);

            if (app()->bound('sentry')) {
                \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($user, $data) {
                    if ($user) {
                        $scope->setUser(['id' => $user->getAuthIdentifier()]);
                    }
                    $scope->setContext('payment_initiation', $data);
                });
                \Sentry\captureException($e);
            }

            throw PaymentException::processingFailed($e->getMessage());
        }
    }

    /**
     * Verify payment status with the gateway.
     *
     * Queries the payment gateway to verify current status and updates
     * the local payment record if payment is confirmed.
     *
     * @param  Authenticatable  $user  The authenticated user
     * @param  string  $transactionId  The transaction or external reference ID
     * @return array{transaction_id: string, status: string, is_paid: bool, gateway_status: string}
     *
     * @throws \App\Exceptions\PaymentException When verification fails
     */
    public function verifyPayment(Authenticatable $user, string $transactionId): array
    {
        try {
            $payment = Payment::where('transaction_id', $transactionId)
                ->orWhere('external_reference', $transactionId)
                ->firstOrFail();

            $statusToken = $this->gatewayManager->queryStatus($transactionId);

            if ($statusToken->isPaid()) {
                $updates = [];

                if ($payment->status !== 'paid') {
                    Log::info('PaymentService: Payment marked as paid', [
                        'payment_id' => $payment->id,
                        'payable_type' => $payment->payable_type,
                        'payable_id' => $payment->payable_id,
                    ]);

                    $updates['status'] = 'paid';
                    $updates['paid_at'] = now();
                    $updates['method'] = $payment->method ?: ($statusToken->rawDetails['payment_method'] ?? ($statusToken->rawDetails['method'] ?? $payment->method));
                }

                if (empty($payment->confirmation_code) && ! empty($statusToken->rawDetails['confirmation_code'])) {
                    $updates['confirmation_code'] = $statusToken->rawDetails['confirmation_code'];
                }

                if (! empty($updates)) {
                    $payment->update($updates);
                }

                // Sync status with payable (only if we transitioned to paid)
                if (($updates['status'] ?? null) === 'paid') {
                    $payable = $payment->payable;
                    if ($payable instanceof \App\Models\Donation) {
                        $payable->update([
                            'status' => 'paid',
                            'payment_id' => $payment->id,
                            'receipt_number' => \App\Models\Donation::generateReceiptNumber(),
                        ]);
                    } elseif ($payable instanceof \App\Models\EventOrder) {
                        $payable->update([
                            'status' => 'paid',
                            'purchased_at' => now(),
                            'waitlist_position' => null,
                            'receipt_number' => $payable->receipt_number ?? \App\Models\EventOrder::generateReceiptNumber(),
                        ]);

                        // Sync registrations to confirmed
                        \App\Models\EventRegistration::where('order_id', $payable->id)
                            ->update([
                                'status' => 'confirmed',
                                'waitlist_position' => null,
                            ]);

                        // Decrement ticket availability
                        if ($payable->ticket) {
                            $payable->ticket->decrement('available', $payable->quantity);
                        }

                        // Increment promo code usage
                        if ($payable->promo_code_id) {
                            \App\Models\PromoCode::where('id', $payable->promo_code_id)->increment('times_used');
                        }
                    } elseif ($payable instanceof \App\Models\LabBooking) {
                        // RE-VALIDATE: Ensure no maintenance or other conflict was created during payment
                        $labService = app(\App\Services\LabBookingService::class);
                        if (!$labService->checkAvailability($payable->lab_space_id, $payable->starts_at, $payable->ends_at, $payable->id)) {
                            Log::warning('PaymentService: Lab booking conflict detected during verification', [
                                'booking_id' => $payable->id,
                                'starts_at' => $payable->starts_at
                            ]);
                            throw new \Exception('One or more of your selected slots are no longer available due to a scheduled maintenance or conflict.');
                        }

                        $payable->update([
                            'status' => \App\Models\LabBooking::STATUS_CONFIRMED,
                            'receipt_number' => \App\Models\LabBooking::generateReceiptNumber(),
                            'quota_consumed' => true,
                        ]);
                    } else {
                        Log::warning('PaymentService: Payable type not handled', [
                            'type' => get_class($payable),
                        ]);
                    }

                    // Dispatch centralized notifications
                    if ($payable instanceof \App\Models\Donation) {
                        $this->notificationService->sendDonationReceived($payable);
                    } elseif ($payable instanceof \App\Models\LabBooking) {
                        try {
                            $this->notificationService->sendLabBookingConfirmation($payable);
                        } catch (\Exception $e) {
                            Log::error('Failed to send lab booking confirmation notification', [
                                'payment_id' => $payment->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    } elseif ($payable instanceof \App\Models\PlanSubscription) {
                        $payer = $payment->payer;
                        if ($payer) {
                            try {
                                $this->notificationService->sendSubscriptionActivated($payable);
                            } catch (\Exception $e) {
                                Log::error('Failed to send subscription notification', [
                                    'payment_id' => $payment->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }

                    AuditLog::log('payment.verified', $payment, null, ['status' => 'paid'], 'Payment verified via gateway');
                }
            } elseif ($statusToken->isFailed() && $payment->status !== 'failed') {
                $payment->update([
                    'status' => 'failed',
                ]);

                $payable = $payment->payable;
                if ($payable instanceof \App\Models\PlanSubscription) {
                    $payer = $payment->payer;
                    if ($payer) {
                        try {
                            $this->notificationService->sendSubscriptionPaymentFailed($payable, $statusToken->status);
                        } catch (\Exception $e) {
                            Log::error('Failed to send subscription failure notification', [
                                'payment_id' => $payment->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                AuditLog::log('payment.failed', $payment, null, ['status' => 'failed'], 'Payment failed at gateway: '.$statusToken->status);
            }

            return [
                'transaction_id' => $transactionId,
                'status' => $payment->status,
                'is_paid' => $statusToken->isPaid(),
                'gateway_status' => $statusToken->status,
            ];

        } catch (\Exception $e) {
            Log::error('Payment verification failed', ['error' => $e->getMessage(), 'id' => $transactionId]);

            if (app()->bound('sentry')) {
                \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($transactionId) {
                    $scope->setContext('payment_verification', ['transaction_id' => $transactionId]);
                });
                \Sentry\captureException($e);
            }

            throw PaymentException::verificationFailed($transactionId, $e->getMessage());
        }
    }

    /**
     * Check payment status by transaction ID.
     *
     * Simple status lookup without gateway verification.
     *
     * @param  string  $transactionId  The transaction or external reference ID
     * @return array{transaction_id: string, status: string, amount: float}
     *
     * @throws \App\Exceptions\PaymentException When payment is not found
     */
    public function checkPaymentStatus(string $transactionId): array
    {
        $idValue = $transactionId;
        $payment = Payment::where('transaction_id', $idValue)
            ->orWhere('external_reference', $idValue)
            ->first();

        if (! $payment) {
            throw PaymentException::notFound($idValue);
        }

        return [
            'transaction_id' => $idValue,
            'status' => $payment->status,
            'amount' => $payment->amount,
        ];
    }

    /**
     * Handle payment webhook.
     *
     * Processes incoming webhook notifications from the payment gateway
     * and updates payment status accordingly.
     *
     * @param  array  $data  Webhook payload containing OrderTrackingId or transaction_id
     * @return array{status: string, message?: string, transaction_id?: string}
     */
    public function handleWebhook(array $data): array
    {
        $transactionId = $data['OrderTrackingId'] ?? $data['transaction_id'] ?? null;
        $notificationType = $data['OrderNotificationType'] ?? 'IPNCHANGE';

        if (! $transactionId) {
            return ['status' => 'error', 'message' => 'No transaction reference found'];
        }

        try {
            // Use a system user or the currently authenticated user for the verification context
            $actor = Auth::user() ?? User::where('email', 'admin@dadisilab.com')->first() ?? new User;

            if ($notificationType === 'RECURRING') {
                return $this->verifyRecurringPayment($actor, $transactionId);
            }

            return $this->verifyPayment($actor, $transactionId);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', ['error' => $e->getMessage()]);

            if (app()->bound('sentry')) {
                \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($data) {
                    $scope->setContext('webhook_payload', $data);
                });
                \Sentry\captureException($e);
            }

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify recurring payment and extend subscription.
     */
    protected function verifyRecurringPayment(Authenticatable $user, string $transactionId): array
    {
        try {
            // 1. Query gateway for full status and metadata
            $statusToken = $this->gatewayManager->queryStatus($transactionId);
            $raw = $statusToken->rawDetails;

            $accountNumber = $raw['account_number'] ?? null;
            $merchantRef = $statusToken->merchantReference;
            $paymentMethod = $raw['payment_method'] ?? 'Unknown';
            $isPaid = $statusToken->isPaid();

            if ($statusToken->isFailed()) {
                Log::warning('Recurring transaction failed at gateway', ['id' => $transactionId]);

                // Try to resolve subscription to notify user
                $subscription = null;
                if ($accountNumber && preg_match('/USER-(\d+)-PLAN-(\d+)$/', $accountNumber, $matches)) {
                    $subscription = \App\Models\PlanSubscription::where('subscriber_id', $matches[1])
                        ->where('plan_id', $matches[2])
                        ->where('status', 'active')
                        ->first();
                }

                if (! $subscription && $merchantRef && preg_match('/SUB_(\d+)/', $merchantRef, $matches)) {
                    $subscription = \App\Models\PlanSubscription::find($matches[1]);
                }

                if ($subscription && $subscription->subscriber) {
                    try {
                        $this->notificationService->sendSubscriptionPaymentFailed($subscription, $statusToken->status);
                    } catch (\Exception $ne) {
                        Log::error('Failed to send recurring failure notification', ['error' => $ne->getMessage()]);
                    }
                }

                return ['status' => 'failed', 'is_paid' => false];
            }

            if (! $isPaid) {
                Log::warning('Recurring transaction requested but not paid', ['id' => $transactionId]);

                return ['status' => 'pending', 'is_paid' => false];
            }

            // 2. Resolve Subscription from account_number (USER-{userId}-PLAN-{planId}) or merchant_reference
            $subscriptionId = null;
            $subscription = null; // Initialize subscription variable
            if ($accountNumber && preg_match('/USER-(\d+)-PLAN-(\d+)$/', $accountNumber, $matches)) {
                $userId = $matches[1];
                $planId = $matches[2];

                $sub = \App\Models\PlanSubscription::where('subscriber_id', $userId)
                    ->where('plan_id', $planId)
                    ->where('status', 'active')
                    ->first();

                if ($sub) {
                    $subscriptionId = $sub->id;
                    $subscription = $sub;
                }
            }

            // Try to find by merchant_reference if not found yet
            if (! $subscriptionId && $merchantRef && preg_match('/SUB_(\d+)/', $merchantRef, $matches)) {
                $subscriptionId = $matches[1];
                if (! isset($subscription)) { // Only fetch if not already set by account_number
                    $subscription = \App\Models\PlanSubscription::find($subscriptionId);
                }
            }

            if (! $subscriptionId || ! isset($subscription)) {
                Log::error('Could not resolve subscription for recurring payment', ['transaction_id' => $transactionId, 'ref' => $merchantRef, 'account_number' => $accountNumber]);
                throw new \Exception('Subscription resolution failed');
            }

            // 3. Check for existing payment record to avoid double-processing
            $existingPayment = Payment::where('transaction_id', $transactionId)->first();
            if ($existingPayment && $existingPayment->status === 'paid') {
                return ['status' => 'paid', 'is_paid' => true, 'message' => 'Already processed'];
            }

            return DB::transaction(function () use ($subscription, $transactionId, $statusToken, $raw, $paymentMethod) {
                // 4. Create NEW Payment record for this recurring charge
                $newPayment = Payment::create([
                    'payer_id' => $subscription->subscriber_id,
                    'amount' => $raw['amount'] ?? ($subscription->plan->invoice_interval === 'year' 
                        ? $subscription->plan->getEffectiveYearlyPrice() 
                        : $subscription->plan->getEffectiveMonthlyPrice()),
                    'currency' => $raw['currency'] ?? 'KES',
                    'gateway' => GatewayManager::getActiveGateway(),
                    'method' => $paymentMethod,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'transaction_id' => $transactionId,
                    'confirmation_code' => $raw['confirmation_code'] ?? null,
                    'external_reference' => $statusToken->merchantReference,
                    'order_reference' => $statusToken->merchantReference, // Required field
                    'payable_type' => 'App\\Models\\PlanSubscription',
                    'payable_id' => $subscription->id,
                    'description' => 'Recurring Renewal: '.($subscription->plan->name ?? 'Subscription'),
                    'meta' => array_merge($raw, ['recurring' => true]),
                ]);

                // 5. Extend Subscription
                $interval = $subscription->plan->invoice_period ?? 'month';
                $count = (int) ($subscription->plan->invoice_interval ?? 1);

                // Carbon::add() with string interval needs proper syntax
                $newEndsAt = $subscription->ends_at->copy();
                match ($interval) {
                    'month' => $newEndsAt->addMonths($count),
                    'year' => $newEndsAt->addYears($count),
                    'week' => $newEndsAt->addWeeks($count),
                    'day' => $newEndsAt->addDays($count),
                    default => $newEndsAt->addMonths($count),
                };

                $subscription->update([
                    'ends_at' => $newEndsAt,
                    'status' => 'active',
                ]);

                // 6. Update SubscriptionEnhancement status to active
                $enh = \App\Models\SubscriptionEnhancement::where('subscription_id', $subscription->id)->first();
                if ($enh) {
                    $enh->update([
                        'payment_method' => $paymentMethod,
                        'last_pesapal_recurring_at' => now(),
                        'status' => 'active',
                    ]);
                }

                Log::info('Recurring payment processed successfully', [
                    'subscription_id' => $subscription->id,
                    'payment_id' => $newPayment->id,
                    'method' => $paymentMethod,
                ]);

                // Notify user about subscription renewal
                try {
                    $this->notificationService->sendSubscriptionActivated($subscription);
                } catch (\Exception $e) {
                    Log::error('Failed to send recurring subscription notification', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                return [
                    'transaction_id' => $transactionId,
                    'status' => 'recurring_processed',
                    'is_paid' => true,
                    'recurring' => true,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Recurring payment verification failed', ['error' => $e->getMessage(), 'id' => $transactionId]);
            throw $e;
        }
    }

    /**
     * Refund a payment.
     *
     * Processes a refund for a previously paid payment.
     * Only payments with 'paid' status can be refunded.
     *
     * @param  Authenticatable  $user  The admin user processing the refund
     * @param  array  $data  Refund data containing transaction_id and optional reason
     * @return array{success: bool, payment_id: int, status: string}
     *
     * @throws \App\Exceptions\PaymentException When refund fails or payment state is invalid
     */
    public function refundPayment(Authenticatable $user, array $data): array
    {
        $transactionId = $data['transaction_id'] ?? null;
        $payment = Payment::where('transaction_id', $transactionId)
            ->orWhere('external_reference', $transactionId)
            ->orWhere('id', $transactionId)
            ->firstOrFail();

        if ($payment->status !== 'paid') {
            throw PaymentException::invalidState('Only paid payments can be refunded');
        }

        try {
            // 1. Initiate refund with gateway
            $result = $this->gatewayManager->refund(
                $payment->transaction_id ?? $payment->external_reference,
                (float) $payment->amount,
                $data['reason'] ?? 'Customer request'
            );

            if (! $result->success) {
                throw PaymentException::refundFailed($result->message ?? 'Gateway refund failed');
            }

            // 2. Update local status on success
            return DB::transaction(function () use ($user, $payment, $data, $result) {
                $payment->update([
                    'status' => 'refunded',
                    'refunded_at' => now(),
                    'refunded_by' => $user->getAuthIdentifier(),
                    'refund_reason' => $data['reason'] ?? 'Customer request',
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'refund_transaction_id' => $result->transactionId,
                        'refund_gateway_response' => $result->rawResponse,
                    ]),
                ]);

                AuditLog::log('payment.refunded', $payment, null, ['status' => 'refunded'], 'Refund processed via Gateway: '.($result->message ?? 'Success'));

                return [
                    'success' => true,
                    'payment_id' => $payment->id,
                    'status' => 'refunded',
                    'refund_transaction_id' => $result->transactionId,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Refund failed', ['error' => $e->getMessage(), 'payment_id' => $payment->id]);
            if ($e instanceof PaymentException) {
                throw $e;
            }
            throw PaymentException::refundFailed($e->getMessage());
        }
    }
}
