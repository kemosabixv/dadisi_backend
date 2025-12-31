<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\Contracts\PlanServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PlanController extends Controller
{
    public function __construct(
        private PlanServiceContract $planService
    ) {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    /**
     * List Active Subscription Plans
     *
     * Retrieves a list of all currently active subscription plans.
     * The response includes comprehensive pricing details (multi-currency support for KES and USD),
     * active promotional campaigns (discounts), and associated feature limits.
     * This endpoint is public and typically used to populate pricing pages.
     *
     * @group Subscription Plans
     * @groupDescription Management of subscription tiers, pricing models, and feature sets. Includes public endpoints for listing plans and administrative endpoints for plan configuration.
     * @authenticated
     * @queryParam include_features boolean optional If true, returns the list of features associated with each plan. Default: true
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 2,
     *       "name": {"en": "Premium Member"},
     *       "pricing": {
     *         "kes": {"base_monthly": 2500, "discounted_monthly": 2000, "base_yearly": 30000, "discounted_yearly": 22500},
     *         "usd": {"base_monthly": 18.52, "discounted_monthly": 14.81, "base_yearly": 222.22, "discounted_yearly": 166.67},
     *         "exchange_rate": 135.00,
     *         "last_updated": "2025-12-28T10:00:00Z"
     *       },
     *       "promotions": {
     *         "monthly": {"discount_percent": 20, "expires_at": "2026-01-15T23:59:59Z", "active": true},
     *         "yearly": {"discount_percent": 25, "expires_at": "2026-01-31T23:59:59Z", "active": true}
     *       },
     *       "features": [
     *         {"id": 1, "name": "Lab Access", "limit": 16},
     *         {"id": 2, "name": "Community Events", "limit": null}
     *       ]
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $includeFeatures = $request->boolean('include_features', true);

            $filters = [
                'include_features' => $includeFeatures,
            ];

            $plans = $this->planService->listActivePlans($filters);

            return response()->json(['success' => true, 'data' => $plans]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve plans', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve plans'], 500);
        }
    }

    /**
     * Get Plan Details
     *
     * Retrieves detailed information for a specific subscription plan by its ID.
     * Includes full pricing breakdowns, active promotions with time remaining, and a complete list of features.
     * Useful for displaying a "Plan Details" modal or checkout summary.
     *
     * @group Subscription Plans
     * @authenticated
     * @urlParam id integer required The unique identifier of the plan. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "name": {"en": "Premium Plan"},
     *     "pricing": {
     *       "kes": {"base_monthly": 2500, "discounted_monthly": 2000, "base_yearly": 30000, "discounted_yearly": 22500},
     *       "usd": {"base_monthly": 17.24, "discounted_monthly": 13.79, "base_yearly": 206.90, "discounted_yearly": 155.17},
     *       "exchange_rate": 145.00,
     *       "last_updated": "2025-12-03T17:13:35Z"
     *     },
     *     "promotions": {
     *       "monthly": {"discount_percent": 20, "expires_at": "2025-12-15T23:59:59Z", "active": true, "time_remaining": "12 days"},
     *       "yearly": {"discount_percent": 25, "expires_at": "2025-12-31T23:59:59Z", "active": true, "time_remaining": "28 days"}
     *     },
     *     "features": [
     *       {"id": 1, "name": {"en": "Feature 1"}, "limit": null},
     *       {"id": 2, "name": {"en": "Feature 2"}, "limit": 100}
     *     ]
     *   }
     * }
     */
    public function show(Plan $plan): JsonResponse
    {
        try {
            $data = $this->planService->getPlanDetails($plan);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve plan', ['error' => $e->getMessage(), 'plan_id' => $plan->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve plan'], 500);
        }
    }

    /**
     * Create Subscription Plan (Admin)
     *
     * Creates a new subscription plan with defined pricing, currency, and optional promotional campaigns.
     * Allows administrators to set up monthly and yearly billing options, assign feature limits, and configure initial discounts.
     *
     * @group Subscription Plans
     * @authenticated
     *
     * @bodyParam name string required The display name of the plan. Example: Premium Plan
     * @bodyParam monthly_price_kes numeric required Base price per month in KES. Must be a positive number. Example: 2500.00
     * @bodyParam currency string required The currency code (currently supports 'KES'). Example: KES
     * @bodyParam monthly_promotion object optional Configuration for monthly billing discounts. Example: {"discount_percent": 20, "expires_at": "2025-12-31T23:59:59Z"}
     * @bodyParam monthly_promotion.discount_percent numeric optional Percentage discount (0-50). Required if monthly_promotion is set. Example: 20
     * @bodyParam monthly_promotion.expires_at string optional ISO 8601 data string for promotion expiry. Example: 2025-12-31T23:59:59Z
     * @bodyParam yearly_promotion object optional Configuration for yearly billing discounts. Example: {"discount_percent": 25, "expires_at": "2026-06-30T23:59:59Z"}
     * @bodyParam yearly_promotion.discount_percent numeric optional Percentage discount (0-50). Required if yearly_promotion is set. Example: 25
     * @bodyParam yearly_promotion.expires_at string optional ISO 8601 date string for promotion expiry. Example: 2026-06-30T23:59:59Z
     * @bodyParam features array optional List of features to include in this plan.
     * @bodyParam features[].id integer required The ID of the feature (from plan_features table). Example: 1
     * @bodyParam features[].limit integer optional usage limit for the feature (null for unlimited). Example: 100
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Plan created successfully",
     *   "data": {
     *     "id": 1,
     *     "name": "Premium Plan"
     *   }
     * }
     *
     * @response 422 {
     *   "message": "Validation errors",
     *   "errors": {
     *     "monthly_price_kes": ["The monthly price kes field is required."]
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'monthly_price_kes' => 'required|numeric|min:' . ((app()->isLocal() || app()->environment('staging')) ? 1 : 100) . '|max:100000',
                'currency' => 'required|string|in:KES',
                'monthly_promotion' => 'nullable|array',
                'monthly_promotion.discount_percent' => 'nullable|numeric|min:0|max:50',
                'monthly_promotion.expires_at' => 'nullable|date|after:now',
                'yearly_promotion' => 'nullable|array',
                'yearly_promotion.discount_percent' => 'nullable|numeric|min:0|max:50',
                'yearly_promotion.expires_at' => 'nullable|date|after:now',
                'features' => 'nullable|array',
                'features.*.name' => 'required_with:features|string|max:255',
                'features.*.limit' => 'nullable|integer|min:-1',
                'features.*.description' => 'nullable|string|max:500',
                'system_features' => 'nullable|array',
                'system_features.*.id' => 'required_with:system_features|integer|exists:system_features,id',
                'system_features.*.value' => 'required_with:system_features|string',
                'system_features.*.display_name' => 'nullable|string|max:255',
                'system_features.*.display_description' => 'nullable|string|max:500',
            ]);

            $plan = $this->planService->createPlan($validated);

            return response()->json([
                'success' => true,
                'message' => 'Plan created successfully',
                'data' => [
                    'id' => $plan->id,
                    'name' => $validated['name'],
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create plan', ['error' => $e->getMessage(), 'data' => $request->all()]);
            return response()->json(['success' => false, 'message' => 'Failed to create plan'], 500);
        }
    }

    /**
     * Update Subscription Plan (Admin)
     *
     * Updates an existing subscription plan's details, pricing, promotions, or active status.
     * Useful for adjusting prices, launching new campaigns, or retiring old plans.
     *
     * @group Subscription Plans

     * @authenticated
     * @urlParam id integer required The plan ID to update
     * @bodyParam name string optional New plan display name. Example: Premium Pro
     * @bodyParam monthly_price_kes numeric optional Updated base monthly price in KES. Must be 100-100,000. Example: 3000
     * @bodyParam monthly_promotion object optional Monthly promotion config (use null to remove). Example: {"discount_percent": 10, "expires_at": "2026-06-04T00:53:33.000000Z"}
     * @bodyParam monthly_promotion.discount_percent numeric optional 0-50% discount percentage. Required when monthly_promotion provided. Example: 10
     * @bodyParam monthly_promotion.expires_at string optional ISO datetime for promo expiry. Required when monthly_promotion provided. Example: 2026-06-04T00:53:33.000000Z
     * @bodyParam yearly_promotion object optional Yearly promotion config (use null to remove). Example: {"discount_percent": 15, "expires_at": "2026-06-04T00:53:33.000000Z"}
     * @bodyParam yearly_promotion.discount_percent numeric optional 0-50% discount percentage. Required when yearly_promotion provided. Example: 15
     * @bodyParam yearly_promotion.expires_at string optional ISO datetime for promo expiry. Required when yearly_promotion provided. Example: 2026-06-04T00:53:33.000000Z
     * @bodyParam is_active boolean optional Activate/deactivate the plan. Example: true
     * @bodyParam features array optional Feature attachments array. Each feature must have a valid ID. No-example
     * @bodyParam features[].id integer required Feature ID (references plan_features table). Required when features provided.
     * @bodyParam features[].limit integer optional Usage limit for this feature (null = unlimited, 0+ for limited). Example: 100
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Plan updated successfully",
     *   "data": {
     *     "id": 1,
     *     "name": "Updated Premium Plan"
     *   }
     * }
     *
     * @response 422 {
     *   "message": "Validation failed",
     *   "errors": {
     *     "features.0.id": ["The features.0.id field is required when features is provided."]
     *   }
     * }
     */
    public function update(Request $request, Plan $plan): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'monthly_price_kes' => 'nullable|numeric|min:' . ((app()->isLocal() || app()->environment('staging')) ? 1 : 100) . '|max:100000',
                'monthly_promotion' => 'nullable|array',
                'monthly_promotion.discount_percent' => 'nullable|numeric|min:0|max:50',
                'monthly_promotion.expires_at' => 'nullable|date|after:now',
                'yearly_promotion' => 'nullable|array',
                'yearly_promotion.discount_percent' => 'nullable|numeric|min:0|max:50',
                'yearly_promotion.expires_at' => 'nullable|date|after:now',
                'is_active' => 'nullable|boolean',
                'features' => 'nullable|array',
                'features.*.name' => 'required_with:features|string|max:255',
                'features.*.limit' => 'nullable|integer|min:-1',
                'features.*.description' => 'nullable|string|max:500',
                'system_features' => 'nullable|array',
                'system_features.*.id' => 'required_with:system_features|integer|exists:system_features,id',
                'system_features.*.value' => 'required_with:system_features|string',
                'system_features.*.display_name' => 'nullable|string|max:255',
                'system_features.*.display_description' => 'nullable|string|max:500',
            ]);

            $plan = $this->planService->updatePlan($plan, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Plan updated successfully',
                'data' => [
                    'id' => $plan->id,
                    'name' => $this->extractPlanName($plan->name),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update plan', ['error' => $e->getMessage(), 'plan_id' => $plan->id]);
            return response()->json(['success' => false, 'message' => 'Failed to update plan'], 500);
        }
    }

    /**
     * Delete Subscription Plan (Admin)
     *
     * Permanently removes a subscription plan from the system.
     * **Restriction:** A plan cannot be deleted if there are currently active subscriptions linked to it. In such cases, consider setting `is_active` to false via the Update endpoint instead.
     *
     * @group Subscription Plans
     * @authenticated
     * @urlParam id integer required The ID of the plan to delete. Example: 1
     * @response 200 {
     *   "message": "Plan deleted"
     * }
     * @response 404 {
     *   "message": "Plan not found"
     * }
     * @response 409 {
     *   "error": "Cannot delete plan with active subscriptions"
     * }
     */
    public function destroy(Plan $plan): JsonResponse
    {
        try {
            $this->planService->deletePlan($plan);

            return response()->json(['message' => 'Plan deleted']);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Cannot delete plan')) {
                return response()->json(['error' => 'Cannot delete plan with active subscriptions'], 409);
            }
            Log::error('Failed to delete plan', ['error' => $e->getMessage(), 'plan_id' => $plan->id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete plan'], 500);
        }
    }

    /**
     * Extract display name from plan name (handles array, JSON string, or plain string)
     *
     * @param mixed $name
     * @return string|mixed
     */
    private function extractPlanName(mixed $name): mixed
    {
        // If it's already an array (from Eloquent cast)
        if (is_array($name)) {
            return $name['en'] ?? reset($name) ?: $name;
        }

        // If it's a string, check if it's JSON
        if (is_string($name)) {
            $trimmed = trim($name);
            if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                $decoded = json_decode($name, true);
                if (is_array($decoded)) {
                    return $decoded['en'] ?? reset($decoded) ?: $name;
                }
            }
        }

        // Return as-is for plain strings or other types
        return $name;
    }
}
