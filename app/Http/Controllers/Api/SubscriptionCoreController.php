<?php

namespace App\Http\Controllers\Api;

use App\DTOs\UpdateRenewalPreferencesDTO;
use App\DTOs\InitiateSubscriptionPaymentDTO;
use App\DTOs\ProcessMockPaymentDTO;
use App\DTOs\CancelSubscriptionDTO;
use App\DTOs\CancelSubscriptionPaymentDTO;
use App\DTOs\ApiResponseDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRenewalPreferencesRequest;
use App\Http\Requests\InitiateSubscriptionPaymentRequest;
use App\Http\Requests\ProcessMockPaymentRequest;
use App\Http\Requests\CancelSubscriptionRequest;
use App\Http\Requests\CancelSubscriptionPaymentRequest;
use App\Services\Contracts\SubscriptionCoreServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @group Subscription Lifecycle
 * @groupDescription Core endpoints for managing the user's subscription state, including status checks, renewal preferences, and payment initiation.
 */
class SubscriptionCoreController extends Controller
{
    public function __construct(
        private SubscriptionCoreServiceContract $subscriptionCoreService
    ) {
        $this->middleware('auth');
    }

    /**
     * Get Current Subscription
     *
     * Retrieves detailed information about the authenticated user's currently active subscription.
     * Includes the plan details, subscription status, expiration dates, and any active enhancements.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "user_id": 2,
     *     "plan": {"id": 2, "name": "Premium Member", "price": 2500},
     *     "subscription": {"id": 1, "status": "active", "expires_at": "2026-12-06T00:00:00Z"},
     *     "enhancement": {"status": "active", "grace_period_ends_at": null}
     *   }
     * }
     */
    public function getCurrentSubscription(Request $request): JsonResponse
    {
        try {
            $data = $this->subscriptionCoreService->getCurrentSubscription($request->user()->id);
            $response = ApiResponseDTO::success([
                'user_id' => $request->user()->id,
                'plan' => $data['plan'] ?? null,
                'subscription' => $data['subscription'] ?? null,
                'enhancement' => $data['enhancement'] ?? null,
            ]);
            return response()->json($response->toArray(), 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve subscription', ['error' => $e->getMessage()]);
            $response = ApiResponseDTO::failure('Failed to retrieve subscription');
            return response()->json($response->toArray(), 500);
        }
    }

    /**
     * Check Subscription Status
     *
     * Provides a high-level overview of the user's subscription status.
     * Returns the current status, details of the active record, and any enhancement flags.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "current_status": "active",
     *     "status_details": {"status": "active", "expires_at": "2026-12-06"},
     *     "enhancements": [{"status": "active"}]
     *   }
     * }
     */
    public function getSubscriptionStatus(Request $request): JsonResponse
    {
        try {
            $data = $this->subscriptionCoreService->getSubscriptionStatus($request->user()->id);
            $response = ApiResponseDTO::success($data);
            return response()->json($response->toArray(), 200);
        } catch (\Exception $e) {
            Log::error('Failed to get status', ['error' => $e->getMessage()]);
            $response = ApiResponseDTO::failure('Failed to retrieve status');
            return response()->json($response->toArray(), 500);
        }
    }

    /**
     * List Available Plans (Simplified)
     *
     * Retrieves a simplified list of active subscription plans.
     * This endpoint is often used for "Upgrade Plan" dropdowns.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {"id": 1, "name": "Free", "price": 0, "features": ["Basic access"]}
     *   ]
     * }
     */
    public function getAvailablePlans(): JsonResponse
    {
        try {
            $plans = $this->subscriptionCoreService->getAvailablePlans();
            $response = ApiResponseDTO::success($plans);
            return response()->json($response->toArray(), 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve plans', ['error' => $e->getMessage()]);
            $response = ApiResponseDTO::failure('Failed to retrieve plans');
            return response()->json($response->toArray(), 500);
        }
    }

    /**
     * Get Renewal Preferences
     *
     * Retrieves the authenticated user's settings for subscription auto-renewal.
     * Includes preferences for reminders, payment methods, and fallback behaviors.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "user_id": 1,
     *     "renewal_type": "automatic",
     *     "send_renewal_reminders": true,
     *     "reminder_days_before": 7
     *   }
     * }
     */
    public function getRenewalPreferences(Request $request): JsonResponse
    {
        try {
            $preferences = $this->subscriptionCoreService->getRenewalPreferences($request->user()->id);
            $response = ApiResponseDTO::success($preferences);
            return response()->json($response->toArray(), 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve preferences', ['error' => $e->getMessage()]);
            $response = ApiResponseDTO::failure('Failed to retrieve preferences');
            return response()->json($response->toArray(), 500);
        }
    }

    /**
     * Update Renewal Preferences
     *
     * Allows the user to configure how their subscription should be handled upon expiration.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @bodyParam renewal_type string optional The renewal mode: "automatic" or "manual". Example: automatic
     * @bodyParam send_renewal_reminders boolean optional Whether to receive email reminders before expiry. Example: true
     * @bodyParam reminder_days_before integer optional Number of days before expiry to send a reminder (1-30). Example: 7
     * @bodyParam preferred_payment_method string optional Preferred payment method. Example: mpesa
     * @bodyParam auto_switch_to_free_on_expiry boolean optional Auto-downgrade if renewal fails. Example: true
     * @bodyParam notes string optional User notes. Example: Prefer text reminders.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Renewal preferences updated successfully",
     *   "data": {"renewal_type": "automatic"}
     * }
     */
    public function updateRenewalPreferences(UpdateRenewalPreferencesRequest $request): JsonResponse
    {
        try {
            $dto = UpdateRenewalPreferencesDTO::fromArray($request->validated());
            $preferences = $this->subscriptionCoreService->updateRenewalPreferences($request->user()->id, $dto->toArray());
            $response = ApiResponseDTO::success($preferences, 'Renewal preferences updated successfully');
            return response()->json($response->toArray(), 200);
        } catch (\Exception $e) {
            Log::error('Failed to update preferences', ['error' => $e->getMessage()]);
            $response = ApiResponseDTO::failure('Failed to update preferences');
            return response()->json($response->toArray(), 500);
        }
    }

    /**
     * Initiate Subscription Payment
     *
     * Starts the payment process for purchasing or renewing a subscription plan.
     * This creates a pending subscription record and initiates a transaction with the payment gateway.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @bodyParam plan_id integer required The ID of the plan to subscribe to. Example: 1
     * @bodyParam billing_period string optional The billing cycle: "month" or "year". Default: month. Example: month
     * @bodyParam phone string optional The mobile number for payment (254xxxxxxxxx). Example: 254712345678
     *
     * @response 201 {
     *   "success": true,
     *   "data": {
     *     "transaction_id": "MOCK_TRAN_987654",
     *     "redirect_url": "https://api.dadisilab.com/api/payments/mock/checkout?id=MOCK_TRAN_987654"
     *   }
     * }
     */
    public function initiatePayment(InitiateSubscriptionPaymentRequest $request): JsonResponse
    {
        try {
            $dto = InitiateSubscriptionPaymentDTO::fromArray($request->validated());
            $result = $this->subscriptionCoreService->initiatePayment($request->user()->id, $dto->toArray());
            $response = ApiResponseDTO::success($result);
            return response()->json($response->toArray(), 201);
        } catch (\Exception $e) {
            Log::error('Payment initiation failed', ['error' => $e->getMessage()]);
            $response = ApiResponseDTO::failure('Payment initiation failed');
            return response()->json($response->toArray(), 500);
        }
    }

    /**
     * Process Mock Payment (Dev/Test)
     *
     * internal/Development endpoint to simulate payment completion.
     * Allows developers to manually trigger a "Success" or "Failure" outcome for a pending transaction.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @bodyParam transaction_id string required The transaction ID returned from the initiation step. Example: MOCK_abc123xyz
     * @bodyParam phone string required The phone number associated with the transaction. Example: 254712345678
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Payment processed successfully",
     *   "data": {"status": "completed"}
     * }
     */
    public function processMockPayment(ProcessMockPaymentRequest $request): JsonResponse
    {
        try {
            $dto = ProcessMockPaymentDTO::fromArray($request->validated());
            $result = $this->subscriptionCoreService->processMockPayment($request->user()->id, $dto->toArray());
            $message = $result['message'] ?? 'Payment processed';
            $response = ApiResponseDTO::success($result, $message);
            return response()->json($response->toArray(), 200);
        } catch (\Exception $e) {
            Log::error('Mock payment failed', ['error' => $e->getMessage()]);
            $response = ApiResponseDTO::failure('Payment processing failed');
            return response()->json($response->toArray(), 500);
        }
    }

    /**
     * Cancel Subscription
     *
     * Terminates the user's active subscription.
     * Depending on business logic, this may take effect immediately or at the end of the current billing cycle.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @bodyParam reason string optional A reason for cancellation (for feedback). Example: Too expensive
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Subscription cancelled successfully"
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "No active subscription found"
     * }
     */
    public function cancelSubscription(CancelSubscriptionRequest $request): JsonResponse
    {
        try {
            $dto = CancelSubscriptionDTO::fromArray($request->validated());
            $result = $this->subscriptionCoreService->cancelSubscription(
                $request->user()->id,
                $dto->reason
            );
            if ($result['success']) {
                $response = ApiResponseDTO::success(
                    data: null,
                    message: $result['message'] ?? 'Subscription cancelled successfully'
                );
                return response()->json($response->toArray(), 200);
            } else {
                $response = ApiResponseDTO::failure($result['message'] ?? 'No active subscription found');
                return response()->json($response->toArray(), 404);
            }
        } catch (\Exception $e) {
            Log::error('Cancellation failed', ['error' => $e->getMessage()]);
            $response = ApiResponseDTO::failure('Failed to cancel subscription');
            return response()->json($response->toArray(), 500);
        }
    }

    /**
     * Cancel a pending subscription payment.
     *
     * @group Subscription Lifecycle
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Payment session cancelled successfully."
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "No pending payment found to cancel."
     * }
     */
    public function cancelSubscriptionPayment(CancelSubscriptionPaymentRequest $request): JsonResponse
    {
        try {
            $dto = CancelSubscriptionPaymentDTO::fromArray($request->validated());
            $result = $this->subscriptionCoreService->cancelSubscriptionPayment(
                $dto->subscriptionId,
                $dto->reason
            );
            $response = ApiResponseDTO::success(
                data: null,
                message: $result['message'] ?? 'Payment session cancelled successfully'
            );
            return response()->json($response->toArray(), 200);
        } catch (\Exception $e) {
            Log::error('Payment cancellation failed', ['error' => $e->getMessage()]);
            $response = ApiResponseDTO::failure('Failed to cancel payment');
            return response()->json($response->toArray(), 500);
        }
    }

    /**
     * Get Subscription Payment by Reference
     * 
     * Retrieves detailed information about a specific subscription payment using its reference.
     * This follows the same pattern as the donation receipt system.
     * 
     * @group Subscription Lifecycle
     * @authenticated
     */
    public function getPaymentByReference(string $reference): JsonResponse
    {
        $user = auth()->user();

        $payment = \App\Models\Payment::where('reference', $reference)
            ->where('payer_id', $user->id)
            ->where(function($query) {
                $query->where('payable_type', 'subscription')
                      ->orWhere('payable_type', 'App\Models\PlanSubscription');
            })
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription payment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\PaymentResource($payment),
        ]);
    }
}
