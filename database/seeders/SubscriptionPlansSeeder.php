<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'price' => null,
                'currency' => 'KES',
                'is_recurring' => false,
                'interval' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Premium',
                'price' => 2500.00,
                'currency' => 'KES',
                'is_recurring' => true,
                'interval' => 'monthly',
                'is_active' => true,
            ],
            [
                'name' => 'Student',
                'price' => 1000.00,
                'currency' => 'KES',
                'is_recurring' => true,
                'interval' => 'yearly',
                'is_active' => true,
            ],
            [
                'name' => 'Corporate',
                'price' => 10000.00,
                'currency' => 'KES',
                'is_recurring' => true,
                'interval' => 'yearly',
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::firstOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }

        $this->command->info('Subscription plans seeded successfully!');
    }
}
