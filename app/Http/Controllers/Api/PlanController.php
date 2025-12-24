<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SystemFeature;
use App\Services\CurrencyService;
use Laravelcm\Subscriptions\Models\Feature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlanController extends Controller
{
    public function __construct()
    {
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
    public function index()
    {
        $plans = Plan::with(['features', 'systemFeatures'])->where('is_active', true)->get()->map(function ($plan) {
            return [
                'id' => $plan->id,
                'name' => json_decode($plan->name, true) ?? [$plan->name],
                'description' => json_decode($plan->description, true)['en'] ?? $plan->description,
                'pricing' => $plan->pricing,
                'promotions' => $plan->promotions,
                'features' => $plan->features->map(function ($feature) {
                    return [
                        'id' => $feature->id,
                        'name' => json_decode($feature->name, true)['en'] ?? $feature->name,
                        'limit' => $feature->pivot?->limit ?? null,
                    ];
                }),
                'system_features' => $plan->systemFeatures->map(function ($sf) {
                    return [
                        'id' => $sf->id,
                        'slug' => $sf->slug,
                        'name' => $sf->name,
                        'value' => $sf->pivot->value,
                        'display_name' => $sf->pivot->display_name ?? $sf->name,
                        'display_description' => $sf->pivot->display_description ?? $sf->description,
                        'value_type' => $sf->value_type,
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
    public function show(Plan $plan)
    {
        $plan->load(['features', 'systemFeatures']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $plan->id,
                'name' => json_decode($plan->name, true) ?? [$plan->name],
                'description' => json_decode($plan->description, true)['en'] ?? $plan->description,
                'pricing' => $plan->pricing,
                'promotions' => $plan->promotions,
                'features' => $plan->features->map(function ($feature) {
                    return [
                        'id' => $feature->id,
                        'name' => json_decode($feature->name, true) ?? [$feature->name],
                        'limit' => $feature->pivot?->limit ?? null,
                    ];
                }),
                'system_features' => $plan->systemFeatures->map(function ($sf) {
                    return [
                        'id' => $sf->id,
                        'slug' => $sf->slug,
                        'name' => $sf->name,
                        'value' => $sf->pivot->value,
                        'display_name' => $sf->pivot->display_name ?? $sf->name,
                        'display_description' => $sf->pivot->display_description ?? $sf->description,
                        'value_type' => $sf->value_type,
                    ];
                }),
            ],
        ]);
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
    public function store(Request $request)
    {
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
            // System Features (new)
            'system_features' => 'nullable|array',
            'system_features.*.id' => 'required_with:system_features|integer|exists:system_features,id',
            'system_features.*.value' => 'required_with:system_features|string',
            'system_features.*.display_name' => 'nullable|string|max:255',
            'system_features.*.display_description' => 'nullable|string|max:500',
        ]);

        $plan = DB::transaction(function () use ($validated) {
            $slug = \Str::slug($validated['name']);

            $plansTable = config('laravel-subscriptions.tables.plans', 'plans');

            $createData = [
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
            ];

            $createData['sort_order'] = Plan::max('sort_order') + 1;

            // Promotional fields only set if the DB table includes them (tests may run against a schema without promotions)
            if (Schema::hasColumn($plansTable, 'monthly_promotion_discount_percent')) {
                $createData['monthly_promotion_discount_percent'] = $validated['monthly_promotion']['discount_percent'] ?? 0;
                $createData['monthly_promotion_expires_at'] = isset($validated['monthly_promotion']['expires_at']) ?
                    $validated['monthly_promotion']['expires_at'] : null;
            }

            if (Schema::hasColumn($plansTable, 'yearly_promotion_discount_percent')) {
                $createData['yearly_promotion_discount_percent'] = $validated['yearly_promotion']['discount_percent'] ?? 0;
                $createData['yearly_promotion_expires_at'] = isset($validated['yearly_promotion']['expires_at']) ?
                    $validated['yearly_promotion']['expires_at'] : null;
            }

            $plan = Plan::create($createData);

            if (!empty($validated['features'])) {
                foreach ($validated['features'] as $featureData) {
                    $plan->features()->create([
                        'name' => json_encode(['en' => $featureData['name']]),
                        'slug' => \Str::slug($featureData['name'] . '-' . $plan->id . '-' . uniqid()),
                        'value' => $featureData['limit'] ?? 'true',
                        'description' => json_encode(['en' => $featureData['description'] ?? '']),
                    ]);
                }
            }

            // Sync system features (new pivot-based features)
            if (!empty($validated['system_features'])) {
                $syncData = [];
                foreach ($validated['system_features'] as $sf) {
                    $syncData[$sf['id']] = [
                        'value' => $sf['value'],
                        'display_name' => $sf['display_name'] ?? null,
                        'display_description' => $sf['display_description'] ?? null,
                    ];
                }
                $plan->systemFeatures()->sync($syncData);
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
    public function update(Request $request, Plan $plan)
    {
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
            // System Features (new)
            'system_features' => 'nullable|array',
            'system_features.*.id' => 'required_with:system_features|integer|exists:system_features,id',
            'system_features.*.value' => 'required_with:system_features|string',
            'system_features.*.display_name' => 'nullable|string|max:255',
            'system_features.*.display_description' => 'nullable|string|max:500',
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
            $plansTable = config('laravel-subscriptions.tables.plans', 'plans');

            if (array_key_exists('monthly_promotion', $validated) && Schema::hasColumn($plansTable, 'monthly_promotion_discount_percent')) {
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

            if (array_key_exists('yearly_promotion', $validated) && Schema::hasColumn($plansTable, 'yearly_promotion_discount_percent')) {
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
                // HasMany doesn't support sync() - manually delete and recreate
                $plan->features()->delete();

                foreach ($validated['features'] as $featureData) {
                    $plan->features()->create([
                        'name' => json_encode(['en' => $featureData['name'] ?? 'Feature']),
                        'slug' => \Str::slug(($featureData['name'] ?? 'feature') . '-' . $plan->id . '-' . ($featureData['id'] ?? uniqid())),
                        'value' => $featureData['limit'] ?? 'true',
                        'description' => json_encode(['en' => $featureData['description'] ?? '']),
                    ]);
                }
            }

            // Sync system features (new pivot-based features)
            if (isset($validated['system_features'])) {
                $syncData = [];
                foreach ($validated['system_features'] as $sf) {
                    $syncData[$sf['id']] = [
                        'value' => $sf['value'],
                        'display_name' => $sf['display_name'] ?? null,
                        'display_description' => $sf['display_description'] ?? null,
                    ];
                }
                $plan->systemFeatures()->sync($syncData);
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
