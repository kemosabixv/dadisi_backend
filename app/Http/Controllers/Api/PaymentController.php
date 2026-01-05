<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateTestMockPaymentRequest;
use App\Http\Requests\Api\CreateTestPesapalPaymentRequest;
use App\Http\Requests\Api\CheckPaymentStatusRequest;
use App\Http\Requests\Api\VerifyPaymentRequest;
use App\Http\Requests\Api\ProcessPaymentRequest;
use App\Http\Requests\Api\HandleWebhookRequest;
use App\Http\Requests\Api\RefundPaymentRequest;
use App\DTOs\Payments\PaymentRequestDTO;
use App\Services\PaymentGateway\PesapalGateway;
use App\Http\Resources\PaymentResource;
use App\Services\Contracts\PaymentServiceContract;
use App\Services\Payments\MockPaymentService;
use App\Exceptions\PaymentException;
use App\Models\PlanSubscription;
use App\Models\SubscriptionEnhancement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Payment Controller
 *
 * Manages payment processing, status checking, and webhook handling
 * Uses MockPaymentService for Phase 1 development/testing (Phase 1)
 */
class PaymentController extends Controller
{
    public function __construct(private PaymentServiceContract $paymentService)
    {
        $this->middleware('auth:sanctum')->except(['handleWebhook', 'checkPaymentStatus', 'showMockPaymentPage', 'completeMockPayment']);
    }

    /**
     * Show mock payment page for local development testing
     *
     * @param string $paymentId The payment tracking ID (e.g., MOCK-SUB-1234567890-ABCDE)
     * @return \Illuminate\View\View|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function showMockPaymentPage(string $paymentId)
    {
        // Initialize context variables
        $payment = null;
        $subscription = null;
        $plan = null;
        $eventOrder = null;
        $event = null;
        $donation = null;
        $cachedData = null;
        $pendingPayment = null;

        // First, check database for pending payment (more reliable)
        $pendingPayment = \App\Models\PendingPayment::findByPaymentId($paymentId);
        
        // Check if payment is expired
        if ($pendingPayment && $pendingPayment->isExpired()) {
            $pendingPayment->markExpired();
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            return redirect($frontendUrl . '/dashboard/subscription?payment=expired');
        }
        
        // Check if already completed
        if ($pendingPayment && $pendingPayment->status === 'completed') {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            return redirect($frontendUrl . '/dashboard/subscription?payment=already_completed');
        }

        // Fallback to cache for backward compatibility
        $cacheKey = 'mock_payment_' . $paymentId;
        $cachedData = cache()->get($cacheKey);

        if ($cachedData) {
            // This is a subscription payment from initiatePayment flow
            Log::info('Mock payment page accessed via cache', ['payment_id' => $paymentId, 'cached_data' => $cachedData]);
            
            // Look up the subscription
            if (isset($cachedData['order_id'])) {
                $subscription = PlanSubscription::with('plan')->find($cachedData['order_id']);
                if ($subscription) {
                    $plan = $subscription->plan;
                }
            }

            // Create a mock payment object for the view
            $payment = (object) [
                'id' => $paymentId,
                'external_reference' => $paymentId,
                'order_reference' => $subscription?->slug ?? ('SUB-' . $cachedData['order_id']),
                'transaction_id' => $cachedData['transaction_id'] ?? null,
                'amount' => $cachedData['amount'] ?? 0,
                'currency' => $cachedData['currency'] ?? 'KES',
                'status' => 'pending',
                'payable_type' => 'App\\Models\\PlanSubscription',
                'payable_id' => $cachedData['order_id'] ?? null,
                'created_at' => $cachedData['created_at'] ?? now()->toIso8601String(),
            ];
        } else {
            // Fall back to looking up Payment model
            $payment = \App\Models\Payment::where('external_reference', $paymentId)
                ->orWhere('order_reference', $paymentId)
                ->orWhere('id', $paymentId)
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                    'payment_id' => $paymentId,
                ], 404);
            }

            // Get the associated payable based on type
            if ($payment->payable_type === 'Laravelcm\\Subscriptions\\Models\\Subscription' || 
                $payment->payable_type === 'App\\Models\\PlanSubscription') {
                $subscription = $payment->payable;
                if ($subscription) {
                    $plan = $subscription->plan;
                }
            } elseif ($payment->payable_type === 'event_order' || 
                      $payment->payable_type === 'App\\Models\\EventOrder') {
                $eventOrder = \App\Models\EventOrder::with('event')->find($payment->payable_id);
                if ($eventOrder) {
                    $event = $eventOrder->event;
                }
            } elseif ($payment->payable_type === 'donation' ||
                      $payment->payable_type === 'App\\Models\\Donation') {
                $donation = \App\Models\Donation::find($payment->payable_id);
            }
        }

        return view('mock-payment', [
            'payment' => $payment,
            'subscription' => $subscription,
            'plan' => $plan,
            'eventOrder' => $eventOrder,
            'event' => $event,
            'donation' => $donation,
            'cachedData' => $cachedData,
        ]);
    }

    /**
     * Complete mock payment (simulate successful payment)
     *
     * @param string $paymentId The payment tracking ID
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function completeMockPayment(Request $request, string $paymentId): \Illuminate\Http\Response|JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        
        // First, check if this is a cached subscription payment (from initiatePayment flow)
        $cacheKey = 'mock_payment_' . $paymentId;
        $cachedData = cache()->get($cacheKey);

        if ($cachedData && is_array($cachedData)) {
            return $this->handleCachedSubscriptionPayment($request, $paymentId, $cachedData, $frontendUrl);
        }

        return $this->handleModelBasedPayment($request, $paymentId, $frontendUrl);
    }

    /**
     * Handle cached subscription payment completion
     */
    private function handleCachedSubscriptionPayment(Request $request, string $paymentId, array $cachedData, string $frontendUrl): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        try {
            DB::beginTransaction();

            $subscriptionId = $cachedData['order_id'] ?? null;
            $subscription = PlanSubscription::find($subscriptionId);

            if (!$subscription) {
                // Subscription not found but cache exists - likely already processed
                cache()->forget('mock_payment_' . $paymentId);
                return redirect($frontendUrl . '/dashboard/subscription?payment=already_processed');
            }

            // DUPLICATE PREVENTION: Check if subscription is already active
            $enhancement = SubscriptionEnhancement::where('subscription_id', $subscription->id)->first();
            if ($enhancement && $enhancement->status === 'active') {
                cache()->forget('mock_payment_' . $paymentId);
                return redirect($frontendUrl . '/dashboard/subscription?payment=success');
            }

            // Update subscription to active
            $subscription->update(['status' => 'active']);
            
            if ($enhancement) {
                $enhancement->update([
                    'status' => 'active',
                    'payment_method' => $request->input('payment_method', 'mpesa')
                ]);
            }

            // Update user subscription status
            $this->updateUserSubscription($cachedData['user_id'] ?? 0, $subscription);

            // Mark pending payment as completed in database
            $pendingPayment = \App\Models\PendingPayment::findByPaymentId($paymentId);
            if ($pendingPayment) {
                $pendingPayment->markCompleted();
            }

            // Clear cache
            cache()->forget('mock_payment_' . $paymentId);

            DB::commit();

            Log::info('Mock subscription payment completed', [
                'payment_id' => $paymentId,
                'subscription_id' => $subscription->id,
            ]);

            return redirect($frontendUrl . '/dashboard/subscription?payment=success');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mock subscription payment completion failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment completion failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user and profile after subscription activation
     */
    private function updateUserSubscription(int $userId, PlanSubscription $subscription): void
    {
        $user = \App\Models\User::find($userId);
        if ($user) {
            // Cancel any other active subscriptions for this user (prevents multiple active subscriptions)
            PlanSubscription::where('subscriber_id', $userId)
                ->where('subscriber_type', 'App\\Models\\User')
                ->where('id', '!=', $subscription->id)
                ->whereNull('canceled_at')
                ->where('status', 'active')
                ->update([
                    'status' => 'cancelled',
                    'canceled_at' => now(),
                ]);

            $user->update([
                'subscription_status' => 'active',
                'active_subscription_id' => $subscription->id,
            ]);

            if ($user->memberProfile) {
                $user->memberProfile->update(['plan_id' => $subscription->plan_id]);
            }
        }
    }

    /**
     * Handle model-based payment completion (for events, donations, etc.)
     */
    private function handleModelBasedPayment(Request $request, string $paymentId, string $frontendUrl): \Illuminate\Http\Response|JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $payment = \App\Models\Payment::where('external_reference', $paymentId)
            ->orWhere('order_reference', $paymentId)
            ->orWhere('id', $paymentId)
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $paymentMethod = $request->input('payment_method', 'mpesa');
            $meta = $payment->meta ?? [];
            $meta['payment_method'] = $paymentMethod;
            
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'meta' => $meta,
            ]);

            // Only process payable if it's a real payment (not a test marker)
            if (!$this->isTestPayment($payment)) {
                $this->activatePayable($payment);
            }

            DB::commit();

            Log::info('Mock payment completed successfully', [
                'payment_id' => $payment->id,
                'external_reference' => $payment->external_reference,
            ]);

            return $this->getPaymentRedirectResponse($payment, $frontendUrl);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mock payment completion failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment completion failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if a payment record is a test marker
     */
    private function isTestPayment(\App\Models\Payment $payment): bool
    {
        return $payment->payable_type === null || 
               $payment->payable_type === 'TestPayment' ||
               str_contains($payment->payable_type ?? '', 'Test') ||
               ($payment->meta['test_payment'] ?? false);
    }

    /**
     * Activate the underlying payable for a model-based payment
     */
    private function activatePayable(\App\Models\Payment $payment): void
    {
        if (!$payment->payable_type || !$payment->payable_id) {
            return;
        }

        $type = $payment->payable_type;
        
        if ($type === 'event_order' || $type === 'App\\Models\\EventOrder') {
            $this->handleEventOrderPayment($payment);
        } elseif ($type === 'donation' || $type === 'App\\Models\\Donation') {
            $this->handleDonationPayment($payment);
        } else {
            $this->handleGenericPayableActivation($payment);
        }
    }

    /**
     * Handle default or legacy payable activation
     */
    private function handleGenericPayableActivation(\App\Models\Payment $payment): void
    {
        $payable = $payment->payable;
        if (!$payable) return;

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
    }

    /**
     * Determine the correct redirect response after model payment
     */
    private function getPaymentRedirectResponse(\App\Models\Payment $payment, string $frontendUrl): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        $type = $payment->payable_type;

        if ($type === 'event_order' || $type === 'App\\Models\\EventOrder') {
            $order = \App\Models\EventOrder::find($payment->payable_id);
            $ref = $order?->reference ?? $payment->order_reference;
            return redirect($frontendUrl . '/checkout/events/success?reference=' . $ref);
        }

        if ($type === 'donation' || $type === 'App\\Models\\Donation') {
            $donation = \App\Models\Donation::find($payment->payable_id);
            $ref = $donation?->reference ?? $payment->order_reference;
            return redirect($frontendUrl . '/dashboard/donations?payment=success&reference=' . $ref);
        }

        return response()->view('mock-payment-success', [
            'payment' => $payment,
            'message' => 'Payment completed successfully!',
        ]);
    }

    /**
     * Cancel mock payment (simulate user clicking cancel at gateway)
     *
     * @param Request $request
     * @param string $paymentId
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function cancelMockPayment(Request $request, string $paymentId): \Illuminate\Http\Response|JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        Log::info('Mock payment cancellation requested', ['payment_id' => $paymentId]);
        
        // First, check if this is a cached subscription payment (from initiatePayment flow)
        $cacheKey = 'mock_payment_' . $paymentId;
        $cachedData = cache()->get($cacheKey);

        if ($cachedData) {
            try {
                DB::beginTransaction();
                
                // Find or create the payment record so webhook processing works correctly
                $payment = \App\Models\Payment::where('external_reference', $paymentId)->first();
                if (!$payment) {
                    $payment = \App\Models\Payment::create([
                        'user_id' => $cachedData['user_id'] ?? null,
                        'amount' => $cachedData['amount'] ?? 0,
                        'currency' => $cachedData['currency'] ?? 'KES',
                        'status' => 'pending',
                        'payable_type' => 'App\\Models\\PlanSubscription',
                        'payable_id' => $cachedData['order_id'] ?? null,
                        'external_reference' => $paymentId,
                        'order_reference' => $paymentId,
                        'gateway' => 'mock_pesapal',
                        'meta' => $cachedData,
                    ]);
                }

                // Process cancellation via common webhook logic
                MockPaymentService::processGenericWebhook([
                    'transaction_id' => $paymentId,
                    'status' => 'cancelled'
                ]);

                // Clear cache
                cache()->forget($cacheKey);

                DB::commit();

                Log::info('Mock subscription payment cancelled successfully', ['payment_id' => $paymentId]);
                return redirect($frontendUrl . '/dashboard/subscription?payment=cancelled');

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Mock subscription payment cancellation failed', [
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);

                return redirect($frontendUrl . '/dashboard/subscription?payment=error&message=' . urlencode($e->getMessage()));
            }
        }

        // Fall back to Payment model lookup
        $payment = \App\Models\Payment::where('external_reference', $paymentId)
            ->orWhere('order_reference', $paymentId)
            ->first();

        if ($payment) {
            MockPaymentService::processGenericWebhook([
                'transaction_id' => $payment->external_reference,
                'status' => 'cancelled'
            ]);
            
            return redirect($frontendUrl . '/dashboard/subscription?payment=cancelled');
        }

        return redirect($frontendUrl . '/dashboard/subscription?payment=not_found');
    }


    /**
     * Handle event order payment completion
     */
    protected function handleEventOrderPayment(\App\Models\Payment $payment): void
    {
        $order = \App\Models\EventOrder::find($payment->payable_id);
        
        if (!$order) {
            Log::warning('Event order not found for payment', [
                'payment_id' => $payment->id,
                'payable_id' => $payment->payable_id,
            ]);
            return;
        }

        // Mark order as paid
        $order->update([
            'status' => 'paid',
            'purchased_at' => now(),
            'receipt_number' => $order->receipt_number ?? \App\Models\EventOrder::generateReceiptNumber(),
        ]);

        // Increment promo code usage if applicable
        if ($order->promo_code_id) {
            \App\Models\PromoCode::where('id', $order->promo_code_id)
                ->increment('times_used');
        }

        Log::info('Event order marked as paid via webhook', [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'payment_id' => $payment->id,
        ]);

        // TODO: Send confirmation email to attendee
    }

    /**
     * Handle donation payment completion
     */
    protected function handleDonationPayment(\App\Models\Payment $payment): void
    {
        $donation = \App\Models\Donation::find($payment->payable_id);
        
        if (!$donation) {
            Log::warning('Donation not found for payment', [
                'payment_id' => $payment->id,
                'payable_id' => $payment->payable_id,
            ]);
            return;
        }

        $donation->update([
            'status' => 'paid',
            'payment_date' => now(),
            'receipt_number' => $donation->receipt_number ?? \App\Models\Donation::generateReceiptNumber(),
        ]);

        Log::info('Donation marked as paid via webhook', [
            'donation_id' => $donation->id,
            'payment_id' => $payment->id,
        ]);

        // TODO: Send thank you email to donor
    }

    /**
     * Create a test mock payment record for development testing
     *
     * @group Payments
     * @authenticated
     *
     * @bodyParam amount numeric required The payment amount. Example: 2500
     * @bodyParam description string optional Payment description. Example: Test subscription payment
     * @bodyParam user_email string optional Email for test user. Example: test@example.com
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Test payment created successfully",
     *   "data": {
     *     "payment_id": 123,
     *     "tracking_id": "MOCK-SUB-1735689600-AB123",
     *     "mock_payment_url": "https://api.dadisilab.com/mock-payment/MOCK-SUB-1735689600-AB123",
     *     "amount": 2500,
     *     "status": "pending"
     *   }
     * }
     */
    public function createTestMockPayment(CreateTestMockPaymentRequest $request): JsonResponse
    {
        // Only allow in local/staging environments
        if (!in_array(app()->environment(), ['local', 'testing', 'staging'])) {
            return response()->json([
                'success' => false,
                'message' => 'Test payments are only available in development environments',
            ], 403);
        }

        $validated = $request->validated();

        $paymentType = $validated['payment_type'] ?? 'test';

        try {
            // Generate unique tracking ID with payment type prefix
            $typePrefix = strtoupper(substr($paymentType, 0, 3));
            $trackingId = "MOCK-{$typePrefix}-" . time() . '-' . strtoupper(substr(md5(uniqid()), 0, 5));
            $orderReference = strtoupper($paymentType) . '-ORDER-' . time();

            // Create the payment record (using placeholder values for test payments since columns are NOT NULL)
            $payment = \App\Models\Payment::create([
                'payable_type' => 'TestPayment', // Marker for test payments (column is NOT NULL)
                'payable_id' => 0,               // Zero indicates no real payable
                'gateway' => 'mock',
                'method' => 'test',
                'status' => 'pending',
                'amount' => $validated['amount'],
                'currency' => 'KES',
                'external_reference' => $trackingId,
                'order_reference' => $orderReference,
                'meta' => [
                    'description' => $validated['description'] ?? 'Test payment',
                    'user_email' => $validated['user_email'] ?? auth()->user()?->email,
                    'test_payment' => true,
                    'created_by' => auth()->id(),
                    'payment_type' => $paymentType, // For routing in webhook
                ],
            ]);

            $mockPaymentUrl = url("/mock-payment/{$trackingId}");

            Log::info('Test mock payment created', [
                'payment_id' => $payment->id,
                'tracking_id' => $trackingId,
                'payment_type' => $paymentType,
                'amount' => $validated['amount'],
                'created_by' => auth()->id(),
            ]);

            \App\Models\AuditLog::log('payment.initiated', $payment, null, [
                'amount' => $payment->amount,
                'payment_type' => $paymentType,
                'tracking_id' => $trackingId
            ], 'Test mock payment created');

            return response()->json([
                'success' => true,
                'message' => 'Test payment created successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'tracking_id' => $trackingId,
                    'order_reference' => $orderReference,
                    'mock_payment_url' => $mockPaymentUrl,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'payment_type' => $paymentType,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create test mock payment', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create test payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a real Pesapal test payment session for admin testing
     * 
     * @group Payments
     * @authenticated
     */
    public function createTestPesapalPayment(CreateTestPesapalPaymentRequest $request): JsonResponse
    {
        // Only allow in local/staging environments
        if (!in_array(app()->environment(), ['local', 'testing', 'staging'])) {
            return response()->json([
                'success' => false,
                'message' => 'Test payments are only available in development environments',
            ], 403);
        }

        $validated = $request->validated();

        try {
            $trackingId = 'TEST-PESAPAL-' . strtoupper(bin2hex(random_bytes(4)));
            $merchantReference = 'TEST-' . time();

            // Create a temporary payment record to track this test
            $payment = \App\Models\Payment::create([
                'user_id' => auth()->id(),
                'transaction_id' => $trackingId,
                'external_reference' => null, // Will be filled after initiation
                'order_reference' => $merchantReference,
                'amount' => $validated['amount'],
                'currency' => 'KES',
                'gateway' => 'pesapal',
                'status' => 'pending',
                'payable_type' => 'test',
                'payable_id' => 0,
                'meta' => [
                    'is_test' => true,
                    'description' => $validated['description'] ?? 'Real Pesapal Sandbox Test',
                    'initiated_from' => 'admin_settings',
                ],
            ]);

            // Explicitly use PesapalGateway even if system default is different
            $gateway = new PesapalGateway();
            
            $paymentRequest = new PaymentRequestDTO(
                amount: (float) $validated['amount'],
                payment_method: 'pesapal',
                currency: 'KES',
                description: $validated['description'] ?? 'Pesapal Sandbox Test',
                reference: $merchantReference,
                email: $validated['user_email'],
                first_name: $validated['first_name'] ?? 'Admin',
                last_name: $validated['last_name'] ?? 'Tester',
                phone: $validated['phone'] ?? '254700000000',
                metadata: [
                    'payment_id' => $payment->id,
                    'is_test' => true,
                ]
            );

            /** @var \App\DTOs\Payments\TransactionResultDTO $result */
            $result = $gateway->initiatePayment($paymentRequest);

            if ($result->success) {
                $payment->update([
                    'external_reference' => $result->transactionId,
                ]);

                Log::info('Real Pesapal test payment initiated', [
                    'payment_id' => $payment->id,
                    'tracking_id' => $trackingId,
                    'order_tracking_id' => $result->transactionId,
                    'amount' => $payment->amount,
                ]);

                \App\Models\AuditLog::log('payment.initiated', $payment, null, [
                    'amount' => $payment->amount,
                    'payment_type' => 'real_pesapal_test',
                    'tracking_id' => $trackingId,
                    'order_tracking_id' => $result->transactionId,
                ], 'Real Pesapal test payment initiated from admin');

                return response()->json([
                    'success' => true,
                    'message' => 'Pesapal test payment initiated',
                    'data' => [
                        'payment_id' => $payment->id,
                        'tracking_id' => $trackingId,
                        'merchant_reference' => $merchantReference,
                        'order_tracking_id' => $result->transactionId,
                        'redirect_url' => $result->redirectUrl,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate Pesapal payment: ' . ($result->message ?? 'Unknown error'),
            ], 400);

        } catch (\Exception $e) {
            Log::error('Failed to create test Pesapal payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate Pesapal payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment form metadata
     *
     * Retrieves configuration data required to render the payment form on the frontend.
     * Includes supported payment methods, currency limits, phone number formatting rules, and test numbers.
     * Use this to dynamically validate user input before submission.
     *
     * @group Payments
     * @groupDescription Endpoints for handling financial transactions, including initiating payments, verifying status, and viewing history. Also includes admin tools for refunds.
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "phone_format": "254XXXXXXXXX",
     *     "phone_example": "254712345678",
     *     "min_amount": 0.01,
     *     "max_amount": 999999.99,
     *     "currency": "KES",
     *     "supported_payment_methods": ["mobile_money", "card"]
     *   }
     * }
     */
    public function getPaymentFormMetadata(): JsonResponse
    {
        try {
            $metadata = $this->paymentService->getPaymentFormMetadata();
            return response()->json([
                'success' => true,
                'data' => $metadata,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve payment form metadata', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment form metadata',
            ], 500);
        }
    }

    /**
     * Check payment status
     *
     * Queries the external payment gateway (or mock service) for the current status of a transaction.
     * Can be called publically to support callback/redirect pages where auth might be tricky, or for quick status checks.
     *
     * @group Payments
     *
     * @queryParam transaction_id string required The unique transaction ID provided during initiation. Example: MOCK_abc123xyz
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "transaction_id": "MOCK_abc123xyz",
     *     "status": "completed",
     *     "amount": 100.00,
     *     "currency": "KES"
     *   }
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "Transaction not found"
     * }
     */
    public function checkPaymentStatus(CheckPaymentStatusRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $status = $this->paymentService->checkPaymentStatus($validated['transaction_id']);
            return response()->json([
                'success' => true,
                'data' => $status, // This returns an array from service, keeping for now or could wrap in resource if it was a model
            ]);
        } catch (PaymentException $e) {
            Log::error('Payment status check failed', ['transaction_id' => $validated['transaction_id'], 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Payment status check failed', ['transaction_id' => $validated['transaction_id'], 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status',
            ], 500);
        }
    }

    public function verifyPayment(VerifyPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = auth()->user();

        try {
            $result = $this->paymentService->verifyPayment($user, $validated['transaction_id']);
            return response()->json([
                'success' => true,
                'message' => 'Payment status retrieved',
                'data' => $result,
            ]);
        } catch (PaymentException $e) {
            Log::error('Payment verification failed', ['user_id' => $user->id, 'transaction_id' => $validated['transaction_id'], 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Payment verification failed', ['user_id' => $user->id, 'transaction_id' => $validated['transaction_id'], 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
            ], 500);
        }
    }

    /**
     * Process payment (Simulation)
     *
     * Simulates the payment processing step for development and testing.
     * Allows forcing different outcomes (success, timeout, failure) based on the phone number provided.
     * See `getPaymentFormMetadata` for test numbers.
     *
     * @group Payments
     * @authenticated
     *
     * @bodyParam transaction_id string required Unique transaction reference. Example: MOCK_abc123xyz
     * @bodyParam order_id integer required The ID of the subscription order being paid for. Example: 1
     * @bodyParam phone string required MPESA phone number. Example: 254712345678
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Payment processed successfully",
     *   "data": {
     *     "status": "completed",
     *     "subscription_status": "active",
     *     "transaction_id": "MOCK_abc123xyz"
     *   }
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "phone": ["The phone format is invalid."]
     *   }
     * }
     */
    public function processPayment(ProcessPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = auth()->user();

        try {
            $result = $this->paymentService->processPayment($user, $validated);
            return response()->json([
                'success' => true,
                'message' => 'Payment initiated',
                'data' => $result,
            ]);
        } catch (PaymentException $e) {
            Log::error('Payment processing failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Payment processing failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
            ], 500);
        }
    }

    /**
     * Get user's payment history
     *
     * Retrieves a paginated history of all subscription payments made by the authenticated user.
     * Includes successful, failed, and pending transactions.
     *
     * @group Payments
     * @authenticated
     *
     * @queryParam per_page integer Number of records per page. Default: 15. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "subscription_id": 1,
     *       "plan_name": "Premium Member",
     *       "amount": 2500.00,
     *       "currency": "KES",
     *       "status": "active",
     *       "failure_state": null,
     *       "started_at": "2025-12-01T10:00:00Z",
     *       "ended_at": "2026-01-01T10:00:00Z"
     *     }
     *   ],
     *   "pagination": {
     *     "total": 1,
     *     "per_page": 15,
     *     "current_page": 1
     *   }
     * }
     */
    public function getPaymentHistory(Request $request): JsonResponse
    {
        $user = auth()->user();
        $perPage = $request->input('per_page', 15);

        try {
            $paginator = $this->paymentService->getPaymentHistory($user, $perPage);
            return response()->json([
                'success' => true,
                'data' => PaymentResource::collection($paginator),
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve payment history', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment history',
            ], 500);
        }
    }

    public function handleWebhook(HandleWebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->paymentService->handleWebhook($validated);
            return response()->json([
                'success' => true,
                'message' => 'Webhook received and queued for processing',
                'event_id' => $result['event_id'],
            ]);
        } catch (\Exception $e) {
            Log::error('Webhook reception failed', ['error' => $e->getMessage(), 'data' => $validated]);
            return response()->json([
                'success' => false,
                'message' => 'Webhook reception failed',
            ], 500);
        }
    }

    /**
     * Handle Pesapal Callback (Browser Redirect)
     */
    public function handlePesapalCallback(Request $request)
    {
        $trackingId = $request->query('OrderTrackingId');
        $merchantRef = $request->query('OrderMerchantReference');
        
        Log::info('Pesapal callback received', [
            'tracking_id' => $trackingId,
            'merchant_ref' => $merchantRef
        ]);

        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        
        // Find the payment
        $payment = \App\Models\Payment::where('external_reference', $trackingId)
            ->orWhere('order_reference', $merchantRef)
            ->first();

        if (!$payment) {
            return redirect($frontendUrl . '/dashboard/subscription?payment=not_found');
        }

        // Determine redirect based on payable type
        $type = $payment->payable_type;
        if ($type === 'event_order' || $type === 'App\\Models\\EventOrder') {
            return redirect($frontendUrl . '/checkout/events/success?reference=' . $merchantRef);
        } elseif ($type === 'donation' || $type === 'App\\Models\\Donation') {
            return redirect($frontendUrl . '/dashboard/donations?payment=success&reference=' . $merchantRef);
        }

        return redirect($frontendUrl . '/dashboard/subscription?payment=processing');
    }

    public function refundPayment(RefundPaymentRequest $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - admin only',
            ], 403);
        }

        $validated = $request->validated();

        try {
            $result = $this->paymentService->refundPayment($user, $validated);
            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => $result,
            ]);
        } catch (PaymentException $e) {
            Log::error('Refund processing failed', ['admin_id' => $user->id, 'transaction_id' => $validated['transaction_id'], 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Refund processing failed', ['admin_id' => $user->id, 'transaction_id' => $validated['transaction_id'], 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Refund processing failed',
            ], 500);
        }
    }
}
