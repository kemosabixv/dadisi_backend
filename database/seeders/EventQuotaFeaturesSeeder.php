<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;
use Illuminate\Support\Str;

class EventQuotaFeaturesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = Plan::all();

        foreach ($plans as $plan) {
            $planName = json_decode($plan->name, true)['en'] ?? $plan->name;

            // Define quotas based on plan name
            $quotas = $this->getQuotasForPlan($planName);

            foreach ($quotas as $slug => $data) {
                // Check if feature already exists for this plan
                $exists = $plan->features()->where('slug', $slug)->exists();
                
                if (!$exists) {
                    $plan->features()->create([
                        'name' => json_encode(['en' => $data['name']]),
                        'slug' => $slug,
                        'value' => (string) $data['limit'],
                        'description' => json_encode(['en' => $data['description']]),
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ]);
                } else {
                    // Update existing
                    $plan->features()->where('slug', $slug)->update([
                        'value' => (string) $data['limit'],
                    ]);
                }
            }
        }
    }

    private function getQuotasForPlan(string $planName): array
    {
        $planName = strtolower($planName);

        if (str_contains($planName, 'community')) {
            return [
                'event_creation_limit' => [
                    'name' => 'Event Creation Limit',
                    'limit' => 0,
                    'description' => 'Maximum events you can create per month'
                ],
                'event_participation_limit' => [
                    'name' => 'Event Participation Limit',
                    'limit' => 2,
                    'description' => 'Maximum events you can attend per month'
                ],
            ];
        }

        if (str_contains($planName, 'student') || str_contains($planName, 'researcher')) {
            return [
                'event_creation_limit' => [
                    'name' => 'Event Creation Limit',
                    'limit' => 2,
                    'description' => 'Maximum events you can create per month'
                ],
                'event_participation_limit' => [
                    'name' => 'Event Participation Limit',
                    'limit' => 5,
                    'description' => 'Maximum events you can attend per month'
                ],
            ];
        }

        // Premium, Corporate, or any other plan
        return [
            'event_creation_limit' => [
                'name' => 'Event Creation Limit',
                'limit' => -1, // Unlimited
                'description' => 'Maximum events you can create per month'
            ],
            'event_participation_limit' => [
                'name' => 'Event Participation Limit',
                'limit' => -1, // Unlimited
                'description' => 'Maximum events you can attend per month'
            ],
        ];
    }
}
