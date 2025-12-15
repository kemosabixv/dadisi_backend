<?php

namespace App\Services;

use App\Models\PlanSubscription;
use App\Models\AutoRenewalJob;
use App\Models\SubscriptionEnhancement;
use App\Models\UserPaymentMethod;
use App\Services\PaymentFailureHandler;
use App\Services\PaymentGateway\GatewayManager;
use Illuminate\Support\Facades\Log;

class AutoRenewalService
{
    protected \App\Services\PaymentGateway\GatewayManager $gatewayManager;

    public function __construct(?\App\Services\PaymentGateway\GatewayManager $gatewayManager = null)
    {
        // Allow DI via service container; fall back to a local instance for backward compatibility
        $this->gatewayManager = $gatewayManager ?? new \App\Services\PaymentGateway\GatewayManager();
    }

    /**
     * Process a single subscription renewal attempt.
     * Creates or updates an AutoRenewalJob and attempts to charge via MockPaymentService.
     */
    public function processSubscriptionRenewal(PlanSubscription $subscription): AutoRenewalJob
    {
        // Some tests and legacy code may set a `user` relation on the subscription.
        // Prefer an explicitly loaded `user` relation if present, otherwise use the
        // package's morphTo `subscriber` relation.
        if ($subscription->relationLoaded('user')) {
            $subscriber = $subscription->getRelationValue('user');
        } else {
            $subscriber = $subscription->subscriber;
        }
        $enh = $subscription->enhancements()->first();

        $job = AutoRenewalJob::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscriber ? $subscriber->id : null,
            'status' => 'processing',
            'attempt_type' => 'initial',
            'attempt_number' => 1,
            'max_attempts' => 3,
            'scheduled_at' => now(),
            'amount' => $subscription->plan->price ?? 0,
            'currency' => $subscription->plan->currency ?? 'KES',
            'metadata' => [
                'plan_id' => $subscription->plan_id,
            ],
        ]);

        // Choose payment identifier:
        // 1) prefer a stored UserPaymentMethod (primary, active)
        // 2) fallback to enhancement.payment_method (legacy)
        // 3) fallback to user's phone or member profile phone
        $paymentIdentifier = null;

        try {
            $stored = null;
            if ($subscriber) {
                $stored = UserPaymentMethod::where('user_id', $subscriber->id)
                    ->where('is_active', true)
                    ->where('is_primary', true)
                    ->first();

                if (!$stored) {
                    $stored = UserPaymentMethod::where('user_id', $subscriber->id)->where('is_active', true)->first();
                }

                if ($stored) {
                    $paymentIdentifier = $stored->identifier;
                }
            }
        } catch (\Exception $e) {
            Log::warning('AutoRenewalService: failed to lookup UserPaymentMethod', ['error' => $e->getMessage(), 'user_id' => $subscriber ? $subscriber->id : null]);
        }

        if (empty($paymentIdentifier)) {
            $paymentIdentifier = $enh->payment_method ?? ($subscriber->phone ?? ($subscriber->memberProfile->phone ?? null) ?? '254701234567');
        }

        // Attempt payment via configured gateway
        try {
            $result = $this->gatewayManager->charge($paymentIdentifier, (int) ($subscription->plan->price ?? 0), ['subscription_id' => $subscription->id]);

            $job->payment_gateway_response = json_encode($result['raw'] ?? $result);
            $job->executed_at = now();
            if (!empty($result['success'])) {
                $job->status = 'succeeded';
                $job->last_renewal_result = 'success';

                // Update enhancement and user subscription status
                if ($enh) {
                    $enh->update([
                        'status' => 'active',
                        'last_renewal_attempt_at' => now(),
                        'last_renewal_result' => 'success',
                        'renewal_attempt_count' => ($enh->renewal_attempt_count ?? 0) + 1,
                    ]);
                }

                $subscription->update(['ends_at' => now()->addMonth()]);
                $subscription->save();
            } else {
                // Failed - delegate failure handling to PaymentFailureHandler
                $job->status = 'failed';
                $job->error_message = $result['error_message'] ?? 'unknown failure';
                $job->last_renewal_result = 'failed';

                if ($enh) {
                    PaymentFailureHandler::handle($enh, $job, $result ?? []);
                }
            }

            $job->save();
        } catch (\Exception $e) {
            Log::error('AutoRenewalService.processSubscriptionRenewal failed', ['error' => $e->getMessage(), 'subscription' => $subscription->id]);
            $job->status = 'failed';
            $job->error_message = $e->getMessage();
            $job->save();
        }

        return $job;
    }
}
