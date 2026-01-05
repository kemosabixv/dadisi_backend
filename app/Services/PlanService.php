<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\SystemFeature;
use App\Services\Contracts\PlanServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Plan Service
 *
 * Handles subscription plan management including pricing, promotions, and feature configuration.
 */
class PlanService implements PlanServiceContract
{
    /**
     * List active plans with optional features
     */
    public function listActivePlans(array $filters = []): \Illuminate\Support\Collection
    {
        try {
            $query = Plan::where('is_active', true)->orderBy('sort_order');

            if (! empty($filters['include_features'])) {
                $query->with(['features', 'systemFeatures']);
            }

            return $query->get()->map(function ($plan) use ($filters) {
                return $this->formatPlanData($plan, ! empty($filters['include_features']));
            });
        } catch (\Exception $e) {
            Log::error('Failed to retrieve plans', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get detailed plan information
     */
    public function getPlanDetails(Plan $plan): array
    {
        try {
            $plan->load(['features', 'systemFeatures']);

            // Get all active system features to ensure the frontend sees all options
            $allFeatures = SystemFeature::active()->sorted()->get();
            $planFeatures = $plan->systemFeatures->keyBy('id');

            return [
                'id' => $plan->id,
                'name' => $this->decodeString($plan->name),
                'description' => $this->decodeString($plan->description),
                'base_monthly_price' => (float) $plan->base_monthly_price,
                'price' => (float) $plan->price,
                'currency' => $plan->currency ?? 'KES',
                'is_active' => (bool) $plan->is_active,
                'requires_student_approval' => (bool) $plan->requires_student_approval,
                'pricing' => $plan->pricing,
                'promotions' => $plan->promotions,
                'features' => $plan->features->map(function ($feature) {
                    return [
                        'id' => $feature->id,
                        'name' => $this->decodeString($feature->name),
                        'limit' => $feature->pivot?->limit ?? null,
                    ];
                }),
                'display_features' => $plan->features->pluck('name')->map(fn ($name) => $this->decodeString($name))->toArray(),
                'system_features' => $allFeatures->map(function ($sf) use ($planFeatures) {
                    $planSf = $planFeatures->get($sf->id);

                    return [
                        'id' => $sf->id,
                        'slug' => $sf->slug,
                        'name' => $sf->name,
                        'value' => $planSf ? $planSf->pivot->value : $sf->default_value,
                        'display_name' => $planSf?->pivot->display_name ?? $sf->name,
                        'display_description' => $planSf?->pivot->display_description ?? $sf->description,
                        'value_type' => $sf->value_type,
                        'is_set' => (bool) $planSf,
                    ];
                }),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve plan', ['error' => $e->getMessage(), 'plan_id' => $plan->id]);
            throw $e;
        }
    }

    /**
     * Create new subscription plan
     */
    public function createPlan(array $data): Plan
    {
        try {
            $plan = DB::transaction(function () use ($data) {
                $slug = Str::slug($data['name']);

                $plansTable = config('laravel-subscriptions.tables.plans', 'plans');

                $createData = [
                    'name' => json_encode(['en' => $data['name']]),
                    'slug' => $slug,
                    'description' => json_encode(['en' => $data['name'].' Plan']),
                    'price' => $data['monthly_price_kes'],
                    'base_monthly_price' => $data['monthly_price_kes'],
                    'signup_fee' => 0,
                    'currency' => $data['currency'],
                    'trial_period' => 0,
                    'trial_interval' => 'day',
                    'grace_period' => 0,
                    'grace_interval' => 'day',
                    'is_active' => true,
                    'sort_order' => Plan::max('sort_order') + 1,
                ];

                if (Schema::hasColumn($plansTable, 'monthly_promotion_discount_percent')) {
                    $createData['monthly_promotion_discount_percent'] = $data['monthly_promotion']['discount_percent'] ?? 0;
                    $createData['monthly_promotion_expires_at'] = $data['monthly_promotion']['expires_at'] ?? null;
                }

                if (Schema::hasColumn($plansTable, 'yearly_promotion_discount_percent')) {
                    $createData['yearly_promotion_discount_percent'] = $data['yearly_promotion']['discount_percent'] ?? 0;
                    $createData['yearly_promotion_expires_at'] = $data['yearly_promotion']['expires_at'] ?? null;
                }

                $plan = Plan::create($createData);

                if (! empty($data['features'])) {
                    $this->attachFeatures($plan, $data['features']);
                }

                if (! empty($data['system_features'])) {
                    $this->attachSystemFeatures($plan, $data['system_features']);
                }

                return $plan;
            });

            return $plan;
        } catch (\Exception $e) {
            Log::error('Failed to create plan', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    /**
     * Update subscription plan
     */
    public function updatePlan(Plan $plan, array $data): Plan
    {
        try {
            DB::transaction(function () use ($plan, $data) {
                $updateData = [];

                if (isset($data['name'])) {
                    $updateData['name'] = json_encode(['en' => $data['name']]);
                    $updateData['slug'] = Str::slug($data['name']);
                    $updateData['description'] = json_encode(['en' => $data['name'].' Plan']);
                }

                if (isset($data['monthly_price_kes'])) {
                    $updateData['price'] = $data['monthly_price_kes'];
                    $updateData['base_monthly_price'] = $data['monthly_price_kes'];
                }

                if (isset($data['is_active'])) {
                    $updateData['is_active'] = $data['is_active'];
                }

                if (isset($data['requires_student_approval'])) {
                    $updateData['requires_student_approval'] = $data['requires_student_approval'];
                }

                $plansTable = config('laravel-subscriptions.tables.plans', 'plans');

                if (array_key_exists('monthly_promotion', $data) && Schema::hasColumn($plansTable, 'monthly_promotion_discount_percent')) {
                    if ($data['monthly_promotion'] === null) {
                        $updateData['monthly_promotion_discount_percent'] = 0;
                        $updateData['monthly_promotion_expires_at'] = null;
                    } else {
                        $updateData['monthly_promotion_discount_percent'] = $data['monthly_promotion']['discount_percent'] ?? 0;
                        $updateData['monthly_promotion_expires_at'] = $data['monthly_promotion']['expires_at'] ?? null;
                    }
                }

                if (array_key_exists('yearly_promotion', $data) && Schema::hasColumn($plansTable, 'yearly_promotion_discount_percent')) {
                    if ($data['yearly_promotion'] === null) {
                        $updateData['yearly_promotion_discount_percent'] = 0;
                        $updateData['yearly_promotion_expires_at'] = null;
                    } else {
                        $updateData['yearly_promotion_discount_percent'] = $data['yearly_promotion']['discount_percent'] ?? 0;
                        $updateData['yearly_promotion_expires_at'] = $data['yearly_promotion']['expires_at'] ?? null;
                    }
                }

                if (! empty($updateData)) {
                    $plan->update($updateData);
                }

                // Handle display_features: convert simple string array to legacy features
                if (isset($data['display_features'])) {
                    $plan->features()->delete();
                    foreach ($data['display_features'] as $featureName) {
                        if (! empty(trim($featureName))) {
                            $plan->features()->create([
                                'name' => json_encode(['en' => $featureName]),
                                'slug' => Str::slug($featureName.'-'.$plan->id.'-'.Str::random(6)),
                                'value' => 'true',
                                'description' => json_encode(['en' => '']),
                            ]);
                        }
                    }
                } elseif (isset($data['features'])) {
                    // Legacy: if features array with objects is passed
                    $plan->features()->delete();
                    $this->attachFeatures($plan, $data['features']);
                }

                if (isset($data['system_features'])) {
                    $this->attachSystemFeatures($plan, $data['system_features']);
                }
            });

            return $plan->fresh();
        } catch (\Exception $e) {
            Log::error('Failed to update plan', ['error' => $e->getMessage(), 'plan_id' => $plan->id]);
            throw $e;
        }
    }

    /**
     * Delete plan if no active subscriptions
     */
    public function deletePlan(Plan $plan): bool
    {
        try {
            if ($plan->subscriptions()->where('ends_at', '>', now())->orWhereNull('ends_at')->exists()) {
                throw new \Exception('Cannot delete plan with active subscriptions');
            }

            $plan->delete();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete plan', ['error' => $e->getMessage(), 'plan_id' => $plan->id]);
            throw $e;
        }
    }

    /**
     * Attach features to plan
     */
    private function attachFeatures(Plan $plan, array $features): void
    {
        foreach ($features as $featureData) {
            $plan->features()->create([
                'name' => json_encode(['en' => $featureData['name']]),
                'slug' => Str::slug($featureData['name'].'-'.$plan->id.'-'.uniqid()),
                'value' => $featureData['limit'] ?? 'true',
                'description' => json_encode(['en' => $featureData['description'] ?? '']),
            ]);
        }
    }

    /**
     * Attach system features to plan
     */
    private function attachSystemFeatures(Plan $plan, array $systemFeatures): void
    {
        $syncData = [];
        foreach ($systemFeatures as $sf) {
            $syncData[$sf['id']] = [
                'value' => $sf['value'],
                'display_name' => $sf['display_name'] ?? null,
                'display_description' => $sf['display_description'] ?? null,
            ];
        }
        $plan->systemFeatures()->sync($syncData);
    }

    /**
     * Format plan data for API response
     */
    private function formatPlanData(Plan $plan, bool $includeFeatures = false): array
    {
        $data = [
            'id' => $plan->id,
            'name' => $this->decodeString($plan->name),
            'description' => $this->decodeString($plan->description),
            'pricing' => $plan->pricing,
            'promotions' => $plan->promotions,
        ];

        if ($includeFeatures) {
            $data['features'] = $plan->features->map(fn ($f) => [
                'id' => $f->id,
                'name' => $this->decodeString($f->name),
                'limit' => $f->pivot?->limit ?? null,
            ]);

            $data['system_features'] = $plan->systemFeatures->map(fn ($sf) => [
                'id' => $sf->id,
                'slug' => $sf->slug,
                'name' => $sf->name,
                'value' => $sf->pivot->value,
                'value_type' => $sf->value_type,
            ]);
        }

        return $data;
    }

    /**
     * Safely decode a JSON string to its English value
     */
    private function decodeString(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && isset($decoded['en'])) {
                $en = $decoded['en'];

                return is_string($en) ? $en : (string) $en;
            }
        }

        return (string) $value;
    }
}
