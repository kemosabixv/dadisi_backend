<?php

namespace App\Services;

use App\Services\Contracts\SubscriptionCoreServiceContract;
use App\Models\User;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\RenewalPreference;
use App\Models\SubscriptionEnhancement;
use App\Models\SystemSetting;
use App\Services\PaymentGateway\GatewayManager;
use App\Services\Payments\MockPaymentService;
use App\DTOs\Payments\PaymentRequestDTO;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Subscription Core Service
 *
 * Handles core subscription lifecycle management including status checks,
 * renewal preferences, and payment initiation.
 */
class SubscriptionCoreService implements SubscriptionCoreServiceContract
{
    public function __construct(
        private GatewayManager $gatewayManager,
        private MockPaymentService $mockPaymentService
    ) {
    }

    /**
     * Get current active subscription with plan details
     * Includes subscriptions marked for end-of-cycle cancellation (still showing as active)
     */
    public function getCurrentSubscription(int $userId): ?array
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return null;
            }

            Log::info('getCurrentSubscription called', [
                'user_id' => $userId,
                'active_subscription_id' => $user->active_subscription_id ?? null,
            ]);

            // First try the activeSubscription relationship
            $subscription = $user->activeSubscription()->with('plan:id,name,description,price')->first();

            // Fallback: direct query using active_subscription_id
            if (!$subscription && $user->active_subscription_id) {
                $subscription = PlanSubscription::with('plan:id,name,description,price')
                    ->find($user->active_subscription_id);
            }

            Log::info('getCurrentSubscription result', [
                'subscription_found' => $subscription ? true : false,
                'subscription_id' => $subscription?->id,
                'plan_id' => $subscription?->plan_id,
                'status' => $subscription?->status,
                'pending_cancellation_at' => $subscription?->pending_cancellation_at,
            ]);

            // Return null only if subscription is actually cancelled (canceled_at is set)
            // Include subscriptions marked for end-of-cycle cancellation
            if (!$subscription || $subscription->canceled_at !== null) {
                return null;
            }

            $enhancement = SubscriptionEnhancement::where('subscription_id', $subscription->id)->first();

            // Normalize plan data
            $planData = $this->formatPlanData($subscription->plan);

            return [
                'user_id' => $userId,
                'plan' => $planData,
                'subscription' => $subscription,
                'enhancement' => $enhancement,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve current subscription', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Get high-level subscription status
     */
    public function getSubscriptionStatus(int $userId): array
    {
        try {
            $user = User::find($userId);
            $currentSubscription = $this->getCurrentSubscription($userId);
            $enhancement = null;
            $currentStatus = 'no_subscription';

            if ($currentSubscription && $currentSubscription['subscription']) {
                $sub = $currentSubscription['subscription'];
                $currentStatus = $sub->status;
                $enhancement = $currentSubscription['enhancement'];
            }

            return [
                'current_status' => $currentStatus,
                'status_details' => $currentSubscription ? $currentSubscription['subscription'] : null,
                'enhancements' => $enhancement ? [$enhancement] : [],
                'history' => [],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get subscription status', ['error' => $e->getMessage(), 'user_id' => $userId]);
            throw $e;
        }
    }

    /**
     * Get available plans
     */
    public function getAvailablePlans(): Collection
    {
        try {
            return Plan::where('is_active', true)->get();
        } catch (\Exception $e) {
            Log::error('Failed to retrieve available plans', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get user's renewal preferences
     */
    public function getRenewalPreferences(int $userId): ?RenewalPreference
    {
        try {
            return RenewalPreference::firstOrCreate(
                ['user_id' => $userId],
                [
                    'renewal_type' => 'automatic',
                    'send_renewal_reminders' => true,
                    'reminder_days_before' => 7,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve renewal preferences', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Update renewal preferences
     */
    public function updateRenewalPreferences(int $userId, array $data): RenewalPreference
    {
        try {
            return DB::transaction(function () use ($userId, $data) {
                $preferences = RenewalPreference::firstOrCreate(
                    ['user_id' => $userId]
                );

                $preferences->update($data);

                Log::info('Renewal preferences updated', [
                    'user_id' => $userId,
                    'auto_renew' => $data['auto_renew'] ?? null,
                ]);

                return $preferences;
            });
        } catch (\Exception $e) {
            Log::error('Failed to update renewal preferences', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Initiate payment for subscription
     */
    public function initiatePayment(int $userId, array $data): array
    {
        try {
            return DB::transaction(function () use ($userId, $data) {
                $user = User::findOrFail($userId);
                $plan = Plan::findOrFail($data['plan_id']);
                $billingPeriod = $data['billing_period'] ?? 'month';
                $paymentGateway = $data['payment_method'] ?? 'mock';

                // Calculate amount based on plan price and billing period
                $amount = $billingPeriod === 'year' ? $plan->price * 12 : $plan->price;

                // Create pending subscription
                $subscription = PlanSubscription::create([
                    'subscriber_id' => $user->id,
                    'subscriber_type' => 'App\\Models\\User',
                    'plan_id' => $plan->id,
                    'starts_at' => now(),
                    'ends_at' => $billingPeriod === 'year' ? now()->addYear() : now()->addMonth(),
                    'name' => $plan->name,
                    'slug' => $plan->slug . '-' . $user->id . '-' . time(),
                ]);

                // Create subscription enhancement with payment pending
                SubscriptionEnhancement::create([
                    'subscription_id' => $subscription->id,
                    'status' => 'payment_pending',
                ]);

                Log::info('Initiating payment', [
                    'user_id' => $userId,
                    'plan_id' => $plan->id,
                    'amount' => $amount,
                    'gateway' => $paymentGateway,
                    'subscription_id' => $subscription->id,
                ]);

                // Recurring details are handled entirely by PesaPal
                $subscriptionDetails = null;
                $accountNumber = "USER-{$userId}-PLAN-{$plan->id}";

                // Create the local payment record
                $payment = \App\Models\Payment::create([
                    'payer_id' => $user->id,
                    'amount' => $amount,
                    'currency' => $data['currency'] ?? 'KES',
                    'payment_method' => $paymentGateway,
                    'description' => $data['description'] ?? 'Subscription Payment - ' . $plan->name,
                    'reference' => 'SUB_' . $subscription->id . '_' . time(),
                    'order_reference' => 'ORDER_' . strtoupper(uniqid()),
                    'status' => 'pending',
                    'payable_type' => 'App\\Models\\PlanSubscription',
                    'payable_id' => $subscription->id,
                    'metadata' => [
                        'user_id' => $userId,
                        'subscription_id' => $subscription->id,
                        'plan_id' => $plan->id,
                        'account_number' => $accountNumber,
                        'subscription_details' => $subscriptionDetails,
                        'enable_auto_renewal' => !empty($data['enable_auto_renewal']),
                    ],
                ]);

                // Convert array to PaymentRequestDTO
                $paymentRequest = new PaymentRequestDTO(
                    amount: (float) $amount,
                    payment_method: $paymentGateway,
                    currency: $data['currency'] ?? 'KES',
                    description: $data['description'] ?? 'Subscription Payment - ' . $plan->name,
                    phone: $data['phone'] ?? null,
                    email: $data['email'] ?? $user->email,
                    reference: $payment->reference,
                    metadata: $payment->metadata
                );

                $gateway = $this->gatewayManager->getGateway();
                $result = $gateway->initiatePayment($paymentRequest);

                if ($result->success) {
                    $payment->update([
                        'transaction_id' => $result->transactionId,
                        'external_reference' => $result->merchantReference,
                        'pesapal_order_id' => $result->transactionId,
                    ]);
                }

                return [
                    'transaction_id' => $result->transactionId,
                    'redirect_url' => $result->redirectUrl,
                    'order_tracking_id' => $result->transactionId,
                    'payment_id' => $payment->id,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Payment initiation failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Process mock payment
     */
    public function processMockPayment(int $userId, array $data): array
    {
        try {
            return DB::transaction(function () use ($userId, $data) {
                $user = User::findOrFail($userId);

                // Find pending subscription with payment_pending enhancement
                $enhancement = SubscriptionEnhancement::where('status', 'payment_pending')
                    ->whereHas('subscription', function ($q) use ($userId) {
                        $q->where('subscriber_id', $userId);
                    })
                    ->first();

                if (!$enhancement) {
                    throw new \Exception('No pending payment found');
                }

                $subscription = $enhancement->subscription;
                $plan = $subscription->plan;
                $amount = $subscription->plan_id ? $plan->price : 0;

                Log::info('Processing mock payment', [
                    'user_id' => $userId,
                    'subscription_id' => $subscription->id,
                    'amount' => $amount,
                ]);

                // Update enhancement status to active
                $enhancement->update(['status' => 'active']);

                // Update subscription status
                $subscription->update(['status' => 'active']);

                // Set user's active subscription
                $user->forceFill(['active_subscription_id' => $subscription->id, 'subscription_status' => 'active'])->save();

                return [
                    'success' => true,
                    'message' => 'Payment processed successfully',
                    'subscription_id' => $subscription->id,
                    'status' => 'completed',
                ];
            });
        } catch (\Exception $e) {
            Log::error('Mock payment processing failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Cancel active subscription
     * 
     * Handles three cancellation scenarios:
     * 1. Paid subscription (status='active'): Mark for end-of-cycle cancellation
     * 2. Payment pending (status='payment_pending'): Cancel immediately
     * 3. Expired subscription: Handle with grace period
     */
    public function cancelSubscription(int $userId, ?string $reason = null): array
    {
        try {
            return DB::transaction(function () use ($userId, $reason) {
                $user = User::findOrFail($userId);
                $subscription = $user->subscription()->first();

                if (!$subscription) {
                    return [
                        'success' => false,
                        'message' => 'No active subscription found',
                    ];
                }

                // Simply cancel the subscription using the package's method
                // The package handles both immediate and end-of-cycle cancellation logic
                $subscription->cancel();

                Log::info('Subscription cancelled', [
                    'user_id' => $userId,
                    'subscription_id' => $subscription->id,
                    'reason' => $reason,
                ]);

                return [
                    'success' => true,
                    'message' => 'Subscription cancelled successfully',
                    'subscription_id' => $subscription->id,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Cancel subscription payment
     */
    public function cancelSubscriptionPayment(int $subscriptionId, ?string $reason = null): array
    {
        try {
            return DB::transaction(function () use ($subscriptionId, $reason) {
                $subscription = PlanSubscription::findOrFail($subscriptionId);

                if ($subscription->status === 'cancelled') {
                    throw new \Exception('Subscription is already cancelled');
                }

                $subscription->update(['status' => 'cancelled']);

                Log::info('Subscription payment cancelled', [
                    'subscription_id' => $subscriptionId,
                    'user_id' => $subscription->user_id,
                    'reason' => $reason,
                ]);

                return [
                    'success' => true,
                    'subscription_id' => $subscriptionId,
                    'status' => 'cancelled',
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription payment', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
            ]);
            throw $e;
        }
    }

    /**
     * Format plan data with normalized attributes
     * Accepts both App\Models\Plan and Laravelcm\Subscriptions\Models\Plan
     */
    private function formatPlanData($plan): array
    {
        $rawName = $plan->getRawOriginal('name');
        $rawDescription = $plan->getRawOriginal('description');

        return [
            'id' => $plan->id,
            'name' => is_string($rawName) ? json_decode($rawName, true) ?? $plan->name : $rawName,
            'description' => is_string($rawDescription) ? json_decode($rawDescription, true) ?? $plan->description : $rawDescription,
            'price' => $plan->price,
        ];
    }
}
