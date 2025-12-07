<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\CurrencyService;
use Laravelcm\Subscriptions\Models\Feature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List All Active Subscription Plans with Multi-Currency Pricing
     *
     * Returns all active subscription plans with comprehensive pricing information including:
     * - Base prices and discounted prices in both KES and USD
     * - Active promotional campaigns with expiration dates
     * - Real-time currency conversion using updated exchange rates
     *
     * Perfect for subscription plan selection interfaces and billing dashboards.
     *
     * @group Plans
     * @authenticated
     * @queryParam include_features boolean optional Include detailed feature information. Default: true
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": {"en": "Premium Plan"},
     *       "pricing": {
     *         "kes": {"base_monthly": 2500, "discounted_monthly": 2000, "base_yearly": 30000, "discounted_yearly": 22500},
     *         "usd": {"base_monthly": 17.24, "discounted_monthly": 13.79, "base_yearly": 206.90, "discounted_yearly": 155.17},
     *         "exchange_rate": 145.00,
     *         "last_updated": "2025-12-03T17:13:35Z"
     *       },
     *       "promotions": {
     *         "monthly": {"discount_percent": 20, "expires_at": "2025-12-15T23:59:59Z", "active": true},
     *         "yearly": {"discount_percent": 25, "expires_at": "2025-12-31T23:59:59Z", "active": true}
     *       },
     *       "features": [
     *         {"id": 1, "name": "Feature 1", "limit": null},
     *         {"id": 2, "name": "Feature 2", "limit": 100}
     *       ]
     *     }
     *   ]
     * }
     */
    public function index()
    {
        $plans = Plan::with('features')->where('is_active', true)->get()->map(function ($plan) {
            return [
                'id' => $plan->id,
                'name' => json_decode($plan->name, true) ?? [$plan->name],
                'pricing' => $plan->pricing,
                'promotions' => $plan->promotions,
                'features' => $plan->features->map(function ($feature) {
                    return [
                        'id' => $feature->id,
                        'name' => json_decode($feature->name, true)['en'] ?? $feature->name,
                        'limit' => $feature->pivot?->limit ?? null,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Get Detailed Plan Information with Multi-Currency Pricing
     *
     * Returns comprehensive information for a specific subscription plan including:
     * - Full pricing breakdown in both KES and USD currencies
     * - Current promotional status and time remaining
     * - Real-time currency conversion and exchange rate used
     * - Detailed feature information with limits
     *
     * Perfect for subscription plan detail pages and purchase flows.
     *
     * @group Plans
     * @authenticated
     * @urlParam id integer required The plan ID
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
    public function show(Plan $plan)
    {
        $plan->load('features');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $plan->id,
                'name' => json_decode($plan->name, true) ?? [$plan->name],
                'pricing' => $plan->pricing,
                'promotions' => $plan->promotions,
                'features' => $plan->features->map(function ($feature) {
                    return [
                        'id' => $feature->id,
                        'name' => json_decode($feature->name, true) ?? [$feature->name],
                        'limit' => $feature->pivot?->limit ?? null,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Create New Subscription Plan with Promotional Pricing
     *
     * Creates a new subscription plan with comprehensive pricing structure and promotional campaigns.
     * Supports flexible monthly pricing with optional promotional discounts for different billing intervals.
     * Only administrators can create subscription plans.
     *
     * @group Plans
     * @authenticated
     * @description Create a new subscription plan with pricing and promotional campaigns. Requires admin privileges.
     *
     * @bodyParam name string required Plan display name. Max 255 characters. Example: Premium Plan
     * @bodyParam monthly_price_kes numeric required Base monthly price in KES. Must be between 100 and 100,000. Example: 2500.00
     * @bodyParam currency string required Currency code. Fixed to "KES". Example: KES
     * @bodyParam monthly_promotion object optional Monthly promotion configuration. Pass null to remove promotion. Example: {"discount_percent": 20, "expires_at": "2025-12-31T23:59:59Z"}
     * @bodyParam monthly_promotion.discount_percent numeric optional Monthly discount percentage. Must be 0-50%. Required when monthly_promotion is provided. Example: 20
     * @bodyParam monthly_promotion.expires_at string optional Monthly promotion expiry date. Required when monthly_promotion provided. Format: ISO 8601. Example: 2025-12-31T23:59:59Z
     * @bodyParam yearly_promotion object optional Yearly promotion configuration. Pass null to remove promotion. Example: {"discount_percent": 25, "expires_at": "2026-06-30T23:59:59Z"}
     * @bodyParam yearly_promotion.discount_percent numeric optional Yearly discount percentage. Must be 0-50%. Required when yearly_promotion is provided. Example: 25
     * @bodyParam yearly_promotion.expires_at string optional Yearly promotion expiry date. Required when yearly_promotion provided. Format: ISO 8601. Example: 2026-06-30T23:59:59Z
     * @bodyParam features array optional Array of plan features to attach. Example: [{"id": 1, "limit": null}, {"id": 2, "limit": 100}]
     * @bodyParam features[].id integer required Feature ID that exists in plan_features table. Required when features provided. Example: 1
     * @bodyParam features[].limit integer optional Usage limit for the feature. Null means unlimited. Example: 100
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'monthly_price_kes' => 'required|numeric|min:100|max:100000',
            'currency' => 'required|string|in:KES',
            'monthly_promotion' => 'nullable|array',
            'monthly_promotion.discount_percent' => 'nullable|numeric|min:0|max:50',
            'monthly_promotion.expires_at' => 'nullable|date|after:now',
            'yearly_promotion' => 'nullable|array',
            'yearly_promotion.discount_percent' => 'nullable|numeric|min:0|max:50',
            'yearly_promotion.expires_at' => 'nullable|date|after:now',
            'features' => 'nullable|array',
            'features.*.id' => 'required_with:features|exists:plan_features,id',
            'features.*.limit' => 'nullable|integer|min:0',
        ]);

        $plan = DB::transaction(function () use ($validated) {
            $slug = \Str::slug($validated['name']);

            $plan = Plan::create([
                'name' => json_encode(['en' => $validated['name']]),
                'slug' => $slug,
                'description' => json_encode(['en' => $validated['name'] . ' Plan']),
                'price' => $validated['monthly_price_kes'], // Store in base price field
                'base_monthly_price' => $validated['monthly_price_kes'], // New field
                'signup_fee' => 0,
                'currency' => $validated['currency'],
                'trial_period' => 0,
                'trial_interval' => 'day',
                'grace_period' => 0,
                'grace_interval' => 'day',
                'is_active' => true,
                'sort_order' => Plan::max('sort_order') + 1,

                // Promotional fields
                'monthly_promotion_discount_percent' => $validated['monthly_promotion']['discount_percent'] ?? 0,
                'monthly_promotion_expires_at' => isset($validated['monthly_promotion']['expires_at']) ?
                    $validated['monthly_promotion']['expires_at'] : null,
                'yearly_promotion_discount_percent' => $validated['yearly_promotion']['discount_percent'] ?? 0,
                'yearly_promotion_expires_at' => isset($validated['yearly_promotion']['expires_at']) ?
                    $validated['yearly_promotion']['expires_at'] : null,
            ]);

            if (!empty($validated['features'])) {
                foreach ($validated['features'] as $featureData) {
                    $plan->features()->attach($featureData['id'], [
                        'limit' => $featureData['limit'] ?? null,
                    ]);
                }
            }

            return $plan;
        });

        return response()->json([
            'success' => true,
            'message' => 'Plan created successfully',
            'data' => [
                'id' => $plan->id,
                'name' => $validated['name'],
            ],
        ], 201);
    }

    /**
     * Update Subscription Plan with Promotional Changes
     *
     * Updates an existing subscription plan including pricing, promotional campaigns, and features.
     * Supports adding, modifying, or removing promotional discounts for both billing intervals.
     *
     * @group Plans
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
    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'monthly_price_kes' => 'nullable|numeric|min:100|max:100000',
            'monthly_promotion' => 'nullable|array',
            'monthly_promotion.discount_percent' => 'nullable|numeric|min:0|max:50',
            'monthly_promotion.expires_at' => 'nullable|date|after:now',
            'yearly_promotion' => 'nullable|array',
            'yearly_promotion.discount_percent' => 'nullable|numeric|min:0|max:50',
            'yearly_promotion.expires_at' => 'nullable|date|after:now',
            'is_active' => 'nullable|boolean',
            'features' => 'nullable|array',
            'features.*.id' => 'required_with:features|exists:plan_features,id',
            'features.*.limit' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($plan, $validated) {
            $updateData = [];

            if (isset($validated['name'])) {
                $updateData['name'] = json_encode(['en' => $validated['name']]);
                $updateData['slug'] = \Str::slug($validated['name']);
                $updateData['description'] = json_encode(['en' => $validated['name'] . ' Plan']);
            }

            if (isset($validated['monthly_price_kes'])) {
                $updateData['price'] = $validated['monthly_price_kes']; // Update base Laravel field
                $updateData['base_monthly_price'] = $validated['monthly_price_kes']; // Update our custom field
            }

            if (isset($validated['is_active'])) {
                $updateData['is_active'] = $validated['is_active'];
            }

            // Handle promotional updates
            if (array_key_exists('monthly_promotion', $validated)) {
                if ($validated['monthly_promotion'] === null) {
                    // Remove monthly promotion
                    $updateData['monthly_promotion_discount_percent'] = 0;
                    $updateData['monthly_promotion_expires_at'] = null;
                } else {
                    // Update monthly promotion
                    $updateData['monthly_promotion_discount_percent'] = $validated['monthly_promotion']['discount_percent'] ?? 0;
                    $updateData['monthly_promotion_expires_at'] = isset($validated['monthly_promotion']['expires_at']) ?
                        $validated['monthly_promotion']['expires_at'] : null;
                }
            }

            if (array_key_exists('yearly_promotion', $validated)) {
                if ($validated['yearly_promotion'] === null) {
                    // Remove yearly promotion
                    $updateData['yearly_promotion_discount_percent'] = 0;
                    $updateData['yearly_promotion_expires_at'] = null;
                } else {
                    // Update yearly promotion
                    $updateData['yearly_promotion_discount_percent'] = $validated['yearly_promotion']['discount_percent'] ?? 0;
                    $updateData['yearly_promotion_expires_at'] = isset($validated['yearly_promotion']['expires_at']) ?
                        $validated['yearly_promotion']['expires_at'] : null;
                }
            }

            if (!empty($updateData)) {
                $plan->update($updateData);
            }

            if (isset($validated['features'])) {
                // Sync features
                $featuresData = [];
                foreach ($validated['features'] as $featureData) {
                    $featuresData[$featureData['id']] = [
                        'limit' => $featureData['limit'] ?? null,
                    ];
                }
                $plan->features()->sync($featuresData);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Plan updated successfully',
            'data' => [
                'id' => $plan->id,
                'name' => json_decode($plan->fresh()->name, true)['en'] ?? $plan->name,
            ],
        ]);
    }

    /**
     * @group Plans
     * @authenticated
     * @urlParam id integer required
     * @response 200 {"message": "Plan deleted"}
     */
    public function destroy(Plan $plan)
    {
        // Check if plan has active subscriptions
        if ($plan->subscriptions()->where('ends_at', '>', now())->orWhereNull('ends_at')->exists()) {
            return response()->json(['error' => 'Cannot delete plan with active subscriptions'], 409);
        }

        $plan->delete();

        return response()->json(['message' => 'Plan deleted']);
    }
}
