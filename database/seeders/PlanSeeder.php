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
                    'trial_period' => 0,
                    'trial_interval' => 'day',
                    'grace_period' => 0,
                    'grace_interval' => 'day',
                    'invoice_period' => 1,
                    'invoice_interval' => 'month',
                    'is_active' => true,
                    'sort_order' => $planData['sort_order'],
                    'requires_student_approval' => $planData['requires_student_approval'] ?? false,
                    'type' => $planData['type'] ?? 'regular',
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
                'display_features' => [
                    'Basic user profile',
                    'Limited dashboard view',
                    'Read-only access to basic content',
                    'View county-specific content',
                    'Community forum support',
                ],
                'system_features' => [
                    'event-participations-monthly' => ['value' => 2],
                    'event-discount-percent' => ['value' => 0],
                    'blog-posts-monthly' => ['value' => 0],
                    'forum-access' => ['value' => true],
                    'forum-threads-monthly' => ['value' => 0],
                    'forum-replies-monthly' => ['value' => 0],
                    'media-storage-mb' => ['value' => 50],
                    'media-max-upload-mb' => ['value' => 5],
                ],
            ],
            [
                'name' => 'Student/Researcher',
                'type' => 'student',
                'monthly_price' => 500,
                'sort_order' => 2,
                'description' => 'Designed for students and researchers, this affordable plan unlocks hands-on learning and collaboration tools. Student verification required.',
                'requires_student_approval' => true,
                'display_features' => [
                    'Enhanced user profile',
                    'Standard dashboard',
                    'Full read/write platform access',
                    '4 hours/month lab space booking',
                    'Basic equipment access (supervised)',
                    'Mentorship request access',
                    'Certificate program progress tracking',
                ],
                'system_features' => [
                    'event-participations-monthly' => ['value' => 5],
                    'event-discount-percent' => ['value' => 10],
                    'blog-posts-monthly' => ['value' => 2],
                    'lab-hours-monthly' => ['value' => 4],
                    'research-collaborators' => ['value' => 2],
                    'forum-access' => ['value' => true],
                    'lab-access' => ['value' => true],
                    'mentorship-access' => ['value' => true],
                    'certificate-programs' => ['value' => true],
                    'media-storage-mb' => ['value' => 200],
                    'media-max-upload-mb' => ['value' => 10],
                ],
            ],
            [
                'name' => 'Premium',
                'type' => 'regular',
                'monthly_price' => 2000,
                'sort_order' => 3,
                'description' => 'Elevate your biotech journey with premium access for serious researchers and professionals. Enjoy priority lab time and advanced tools.',
                'requires_student_approval' => false,
                'display_features' => [
                    'Full verified profile',
                    'Advanced dashboard',
                    '16 hours/month lab space booking',
                    'Advanced equipment access (with training)',
                    'Track up to 50 samples',
                    'Access to mentor network',
                    'Digital certificates',
                    'Priority email support (24h response)',
                ],
                'system_features' => [
                    'event-participations-monthly' => ['value' => 0], // Unlimited
                    'event-discount-percent' => ['value' => 20],
                    'blog-posts-monthly' => ['value' => 10],
                    'lab-hours-monthly' => ['value' => 16],
                    'research-collaborators' => ['value' => 10],
                    'sample-tracking-limit' => ['value' => 50],
                    'forum-access' => ['value' => true],
                    'lab-access' => ['value' => true],
                    'mentorship-access' => ['value' => true],
                    'certificate-programs' => ['value' => true],
                    'priority-support' => ['value' => true],
                    'media-storage-mb' => ['value' => 1024],
                    'media-max-upload-mb' => ['value' => 50],
                ],
            ],
            [
                'name' => 'Corporate/Enterprise',
                'type' => 'regular',
                'monthly_price' => 10000,
                'sort_order' => 4,
                'description' => 'Empower your organization with enterprise-level biotech solutions. Manage teams and access unlimited lab resources.',
                'requires_student_approval' => false,
                'display_features' => [
                    'Team profiles with admin control',
                    'Custom team dashboard',
                    '40+ hours/month lab space booking',
                    'Full equipment suite with training',
                    'Unlimited research collaborators',
                    'API access for integrations',
                    'Dedicated account manager',
                ],
                'system_features' => [
                    'event-participations-monthly' => ['value' => 0],
                    'event-discount-percent' => ['value' => 30],
                    'blog-posts-monthly' => ['value' => 0],
                    'lab-hours-monthly' => ['value' => 40],
                    'research-collaborators' => ['value' => 0],
                    'sample-tracking-limit' => ['value' => 0],
                    'forum-access' => ['value' => true],
                    'lab-access' => ['value' => true],
                    'mentorship-access' => ['value' => true],
                    'certificate-programs' => ['value' => true],
                    'priority-support' => ['value' => true],
                    'api-access' => ['value' => true],
                    'media-storage-mb' => ['value' => 5120],
                    'media-max-upload-mb' => ['value' => 100],
                ],
            ],
        ];
    }
}
