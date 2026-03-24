<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds (idempotent).
     */
    public function run(): void
    {
        $plans = $this->getPlansData();

        foreach ($plans as $planData) {
            $slug = Str::slug($planData['name']);

            $plan = Plan::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => ['en' => $planData['name']],
                    'description' => ['en' => $planData['description']],
                    'base_monthly_price' => $planData['monthly_price'],
                    'price' => $planData['monthly_price'],
                    'signup_fee' => 0,
                    'currency' => 'KES',
                    'grace_period' => 0,
                    'grace_interval' => 'day',
                    'invoice_period' => 1,
                    'invoice_interval' => 'month',
                    'is_active' => true,
                    'sort_order' => $planData['sort_order'],
                    'requires_student_approval' => $planData['requires_student_approval'] ?? false,
                    'type' => $planData['type'] ?? 'regular',
                    'media_storage_limit_mb' => $planData['media_storage_limit_mb'] ?? 20,
                ]
            );

            // Sync system features
            if (isset($planData['system_features'])) {
                $syncData = [];
                foreach ($planData['system_features'] as $slug => $featureData) {
                    $systemFeature = \App\Models\SystemFeature::where('slug', $slug)->first();
                    if ($systemFeature) {
                        $syncData[$systemFeature->id] = [
                            'value' => (string) $featureData['value'],
                            'display_name' => $featureData['display_name'] ?? $systemFeature->name,
                            'display_description' => $featureData['display_description'] ?? $systemFeature->description,
                        ];
                    }
                }
                $plan->systemFeatures()->sync($syncData);
            }

            // Still seed legacy features for compatibility if needed,
            // but the goal is to move to systemFeatures.
            // For now, let's keep descriptive features for UI display if the frontend still uses them.
            $plan->features()->forceDelete();
            foreach ($planData['display_features'] as $featureName) {
                $plan->features()->create([
                    'name' => ['en' => $featureName],
                    'slug' => Str::slug($featureName.'-'.$plan->id.'-'.Str::random(6)),
                    'value' => 'true',
                    'description' => ['en' => ''],
                ]);
            }
        }

        $this->command->info('PlanSeeder completed: '.count($plans).' plans seeded with system features.');
    }

    private function getPlansData(): array
    {
        return [
            [
                'name' => 'Community',
                'type' => 'regular',
                'monthly_price' => 0,
                'sort_order' => 1,
                'description' => 'Join the Dadisi Labs community for free and explore the world of biotech. Access basic tutorials, community events, and forums to learn, connect, and get inspired.',
                'requires_student_approval' => false,
                'media_storage_limit_mb' => 20,
                'display_features' => [
                    'Free access to Lab spaces',
                    'Community forum access',
                    'Basic project dashboard',
                    'County-level networking',
                    'Interactive community sessions',
                ],
                'system_features' => [
                    'ticket_discount_percent' => ['value' => 0],
                    'waitlist_priority' => ['value' => false],
                    'blog-posts-monthly' => ['value' => 0],
                    'forum_thread_limit' => ['value' => 2],
                    'forum_reply_limit' => ['value' => 5],
                    'monthly_chat_message_limit' => ['value' => 150],
                    'media-storage-mb' => ['value' => 20],
                ],
            ],
            [
                'name' => 'Student/Researcher',
                'type' => 'student',
                'monthly_price' => 500,
                'sort_order' => 2,
                'description' => 'Designed for students and researchers, this affordable plan unlocks hands-on learning and collaboration tools. Student verification required.',
                'requires_student_approval' => true,
                'media_storage_limit_mb' => 100,
                'display_features' => [
                    '15% Event ticket discounts',
                    'Priority waitlist access',
                    '4 Hours/month Lab booking',
                    'Full platform interaction',
                    'Direct collaboration tools',
                ],
                'system_features' => [
                    'ticket_discount_percent' => ['value' => 15],
                    'waitlist_priority' => ['value' => true],
                    'blog-posts-monthly' => ['value' => 2],
                    'lab_hours_monthly' => ['value' => 4],
                    'forum_thread_limit' => ['value' => 10],
                    'forum_reply_limit' => ['value' => 50],
                    'monthly_chat_message_limit' => ['value' => 6000],
                    'media-storage-mb' => ['value' => 100],
                ],
            ],
            [
                'name' => 'Premium',
                'type' => 'regular',
                'monthly_price' => 2000,
                'sort_order' => 3,
                'description' => 'Elevate your biotech journey with premium access for serious researchers and professionals. Enjoy priority lab time and advanced tools.',
                'requires_student_approval' => false,
                'media_storage_limit_mb' => 200,
                'display_features' => [
                    '25% Event ticket discounts',
                    'Priority waitlist access',
                    '16 Hours/month Lab booking',
                    'Automatic booking approval',
                    'Unlimited forum participation',
                    'Advanced dashboard analytics',
                ],
                'system_features' => [
                    'ticket_discount_percent' => ['value' => 25],
                    'waitlist_priority' => ['value' => true],
                    'blog-posts-monthly' => ['value' => 10],
                    'lab_hours_monthly' => ['value' => 16],
                    'forum_thread_limit' => ['value' => -1],
                    'forum_reply_limit' => ['value' => -1],
                    'monthly_chat_message_limit' => ['value' => -1],
                    'media-storage-mb' => ['value' => 200],
                ],
            ],
        ];
    }
}
