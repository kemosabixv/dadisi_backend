<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Plan; // Changed to use our custom Plan model

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
                'base_monthly_price' => 0.00,  // No monthly pricing for free plan
                'yearly_discount_percent' => 0.00,
                'yearly_promotion_discount_percent' => 0,
                'yearly_promotion_expires_at' => null,
                'default_billing_period' => 0,
                'currency' => 'KES',
                'is_active' => true,
                'invoice_period' => 0,
                'invoice_interval' => 'day',
            ],
            [
                'name' => 'Premium',
                'base_monthly_price' => 2500.00,            // KSh 2,500 per month
                'yearly_discount_percent' => 0.00,          // No hardcoded discount
                'yearly_promotion_discount_percent' => 15,  // 15% promotional discount
                'yearly_promotion_expires_at' => now()->addMonths(6)->toDateTimeString(), // 6 months active
                'default_billing_period' => 1,              // Monthly by default
                'currency' => 'KES',
                'is_active' => true,
                'invoice_period' => 1,                      // 1 month billing cycle
                'invoice_interval' => 'month',
            ],
            [
                'name' => 'Student',
                'base_monthly_price' => 1000.00,            // KSh 1,000 per month
                'yearly_discount_percent' => 0.00,          // No hardcoded discount
                'yearly_promotion_discount_percent' => 20,  // 20% promotional discount
                'yearly_promotion_expires_at' => now()->addMonths(12)->toDateTimeString(), // 12 months active
                'default_billing_period' => 12,             // Annual by default
                'currency' => 'KES',
                'is_active' => true,
                'invoice_period' => 12,                     // 12 month billing cycle
                'invoice_interval' => 'month',
            ],
            [
                'name' => 'Corporate',
                'base_monthly_price' => 10000.00,           // KSh 10,000 per month
                'yearly_discount_percent' => 0.00,          // No hardcoded discount
                'yearly_promotion_discount_percent' => 25,  // 25% promotional discount
                'yearly_promotion_expires_at' => now()->addMonths(3)->toDateTimeString(), // 3 months active
                'default_billing_period' => 12,             // Annual by default
                'currency' => 'KES',
                'is_active' => true,
                'invoice_period' => 12,                     // 12 month billing cycle
                'invoice_interval' => 'month',
            ],
        ];

        foreach ($plans as $plan) {
            $slug = Str::slug($plan['name']);
            $nameJson = json_encode(['en' => $plan['name']]);

            // Calculate legacy price field for backward compatibility
            $legacyPrice = $plan['base_monthly_price'] > 0 ? $plan['base_monthly_price'] : 0;

            Plan::firstOrCreate(
                ['slug' => $slug],
                array_merge($plan, [
                    'name' => $nameJson,
                    'slug' => $slug,
                    'description' => json_encode(['en' => $plan['name'] . ' Plan']),
                    'price' => $legacyPrice, // Legacy field for backward compatibility
                    'signup_fee' => 0.00,
                    'trial_period' => 0,
                    'trial_interval' => 'day',
                    'grace_period' => 0,
                    'grace_interval' => 'day',
                    'sort_order' => 0,
                ])
            );
        }

        $this->command->info('Subscription plans seeded successfully with promotional pricing!');
        $this->command->info('Pricing structure:');
        $this->command->info('- Premium: KSh 2,500/month, base yearly KSh 30,000 (15% promotional discount = KSh 21,250)');
        $this->command->info('- Student: KSh 1,000/month, base yearly KSh 12,000 (20% promotional discount = KSh 9,600)');
        $this->command->info('- Corporate: KSh 10,000/month, base yearly KSh 120,000 (25% promotional discount = KSh 90,000)');
    }
}
