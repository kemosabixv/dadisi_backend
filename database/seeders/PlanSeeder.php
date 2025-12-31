<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Plan;

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
                    'name' => json_encode(['en' => $planData['name']]),
                    'description' => json_encode(['en' => $planData['description']]),
                    'base_monthly_price' => $planData['monthly_price'],
                    'price' => $planData['monthly_price'],
                    'signup_fee' => 0,
                    'currency' => 'KES',
                    'trial_period' => 0,
                    'trial_interval' => 'day',
                    'grace_period' => 0,
                    'grace_interval' => 'day',
                    'is_active' => true,
                    'sort_order' => $planData['sort_order'],
                ]
            );

            // Clear existing features and recreate
            $plan->features()->delete();

            // Add display features (descriptive)
            foreach ($planData['features'] as $featureName) {
                $plan->features()->create([
                    'name' => json_encode(['en' => $featureName]),
                    'slug' => Str::slug($featureName . '-' . $plan->id . '-' . Str::random(6)),
                    'value' => 'true',
                    'description' => json_encode(['en' => '']),
                ]);
            }

            // Add quota features (machine-readable for enforcement)
            foreach ($planData['quotas'] as $quota) {
                $plan->features()->create([
                    'name' => json_encode(['en' => $quota['name']]),
                    'slug' => $quota['slug'] . '-' . $plan->id,
                    'value' => (string) $quota['limit'], // 0 = unlimited
                    'description' => json_encode(['en' => $quota['description']]),
                    'resettable_period' => $quota['resettable_period'] ?? 1,
                    'resettable_interval' => $quota['resettable_interval'] ?? 'month',
                ]);
            }
        }

        $this->command->info('PlanSeeder completed: ' . count($plans) . ' plans seeded with quotas.');
    }

    private function getPlansData(): array
    {
        return [
            [
                'name' => 'Community',
                'monthly_price' => 0,
                'sort_order' => 1,
                'description' => 'Join the Dadisi Labs community for free and explore the world of biotech. Access basic tutorials, community events, and forums to learn, connect, and get inspired. Perfect for curious individuals, students, and locals looking to dip their toes into biotech and community science without any commitment.',
                'features' => [
                    'Basic user profile',
                    'Limited dashboard view',
                    'Read-only access to basic content',
                    'View county-specific content',
                    'Community forum support',
                    '2 event participations per month',
                    'Read and post in community forums',
                    'Read blogs (comment only)',
                    'Basic educational tutorials',
                ],
                'quotas' => [
                    [
                        'name' => 'Monthly Event Participations',
                        'slug' => 'event-participations-monthly',
                        'limit' => 2,
                        'description' => 'Number of events you can participate in per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Event Discount Percent',
                        'slug' => 'event-discount-percent',
                        'limit' => 0,
                        'description' => 'Discount percentage on paid events',
                        'resettable_period' => 0,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Blog Posts',
                        'slug' => 'blog-posts-monthly',
                        'limit' => 0,
                        'description' => 'Number of blog posts you can publish per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Forum Threads',
                        'slug' => 'forum-threads-monthly',
                        'limit' => 0, // Unlimited for all plans
                        'description' => 'Number of forum threads you can create per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Forum Replies',
                        'slug' => 'forum-replies-monthly',
                        'limit' => 0, // Unlimited for all plans
                        'description' => 'Number of forum replies you can post per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                ],
            ],
            [
                'name' => 'Student/Researcher',
                'monthly_price' => 500,
                'sort_order' => 2,
                'description' => 'Designed for students and researchers, this affordable plan unlocks hands-on learning and collaboration tools. Gain access to lab spaces, equipment, and mentorship while building practical biotech skills. Collaborate with peers, attend discounted events, and track your progress toward certifications—all at a student-friendly price.',
                'features' => [
                    'Enhanced user profile',
                    'Standard dashboard',
                    'Full read/write platform access',
                    'County content filtering and contribution',
                    'Email support (72h response)',
                    '4 hours/month lab space booking',
                    'Basic equipment access (supervised)',
                    '2 research collaborators',
                    'Online safety training modules',
                    'Basic lab report templates',
                    '5 event participations per month with 10% discount',
                    'Create free events',
                    'Read and post in community forums',
                    'Read blogs and post up to 2 per month',
                    'All tutorials and webinars',
                    'Mentorship request access',
                    'Certificate program progress tracking',
                ],
                'quotas' => [
                    [
                        'name' => 'Monthly Event Participations',
                        'slug' => 'event-participations-monthly',
                        'limit' => 5,
                        'description' => 'Number of events you can participate in per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Event Discount Percent',
                        'slug' => 'event-discount-percent',
                        'limit' => 10,
                        'description' => 'Discount percentage on paid events',
                        'resettable_period' => 0,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Blog Posts',
                        'slug' => 'blog-posts-monthly',
                        'limit' => 2,
                        'description' => 'Number of blog posts you can publish per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Lab Hours',
                        'slug' => 'lab-hours-monthly',
                        'limit' => 4,
                        'description' => 'Hours of lab space booking per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Research Collaborators',
                        'slug' => 'research-collaborators',
                        'limit' => 2,
                        'description' => 'Number of research collaborators allowed',
                        'resettable_period' => 0,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Forum Threads',
                        'slug' => 'forum-threads-monthly',
                        'limit' => 0, // Unlimited for all plans
                        'description' => 'Number of forum threads you can create per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Forum Replies',
                        'slug' => 'forum-replies-monthly',
                        'limit' => 0, // Unlimited for all plans
                        'description' => 'Number of forum replies you can post per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                ],
            ],
            [
                'name' => 'Premium',
                'monthly_price' => 2000,
                'sort_order' => 3,
                'description' => 'Elevate your biotech journey with premium access for serious researchers and professionals. Enjoy priority lab time, advanced tools, and unlimited collaboration. Create paid events, earn digital certificates, and access a mentor network—ideal for small labs and those seeking career advancement in biotech.',
                'features' => [
                    'Full verified profile',
                    'Advanced dashboard',
                    'Full platform access with contribution',
                    'County content contribution and management',
                    'Priority email support (24h response)',
                    '16 hours/month lab space booking',
                    'Advanced equipment access (with training)',
                    '10 research collaborators',
                    'Track up to 50 samples',
                    'Online and practical safety training',
                    'Advanced lab report templates',
                    'Unlimited event participations with 20% discount',
                    'Create paid events',
                    'Read and post in community forums',
                    'Read blogs and post up to 10 per month',
                    'Advanced courses and webinars',
                    'Access to mentor network',
                    'Digital certificates',
                ],
                'quotas' => [
                    [
                        'name' => 'Monthly Event Participations',
                        'slug' => 'event-participations-monthly',
                        'limit' => 0, // 0 = unlimited
                        'description' => 'Number of events you can participate in per month (unlimited)',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Event Discount Percent',
                        'slug' => 'event-discount-percent',
                        'limit' => 20,
                        'description' => 'Discount percentage on paid events',
                        'resettable_period' => 0,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Blog Posts',
                        'slug' => 'blog-posts-monthly',
                        'limit' => 10,
                        'description' => 'Number of blog posts you can publish per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Lab Hours',
                        'slug' => 'lab-hours-monthly',
                        'limit' => 16,
                        'description' => 'Hours of lab space booking per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Research Collaborators',
                        'slug' => 'research-collaborators',
                        'limit' => 10,
                        'description' => 'Number of research collaborators allowed',
                        'resettable_period' => 0,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Sample Tracking Limit',
                        'slug' => 'sample-tracking-limit',
                        'limit' => 50,
                        'description' => 'Maximum number of samples to track',
                        'resettable_period' => 0,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Forum Threads',
                        'slug' => 'forum-threads-monthly',
                        'limit' => 0, // Unlimited for all plans
                        'description' => 'Number of forum threads you can create per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Forum Replies',
                        'slug' => 'forum-replies-monthly',
                        'limit' => 0, // Unlimited for all plans
                        'description' => 'Number of forum replies you can post per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                ],
            ],
            [
                'name' => 'Corporate/Enterprise',
                'monthly_price' => 10000,
                'sort_order' => 4,
                'description' => 'Empower your organization with enterprise-level biotech solutions. Manage teams, access unlimited lab resources, and integrate custom workflows. From white-label branding to dedicated mentors and API access, this plan supports large-scale research, talent development, and business partnerships—tailored to your corporate needs.',
                'features' => [
                    'Team profiles with admin control',
                    'Custom team dashboard',
                    'White-label/custom branding',
                    'Multi-county management',
                    'Dedicated account manager',
                    '40+ hours/month lab space booking with priority booking',
                    'Full equipment suite with training',
                    'Unlimited research collaborators',
                    'Unlimited sample tracking with barcode system',
                    'Custom team safety training',
                    'Custom report generation',
                    'Unlimited event participations with 30% discount and bulk options',
                    'Create sponsored events',
                    'Read and post in community forums',
                    'Read blogs and unlimited posting with featured placement',
                    'Private team forums',
                    'Custom training programs',
                    'Dedicated industry mentors',
                    'Accredited certifications',
                    'Unlimited projects and team channels',
                    'API access for integrations',
                ],
                'quotas' => [
                    [
                        'name' => 'Monthly Event Participations',
                        'slug' => 'event-participations-monthly',
                        'limit' => 0, // 0 = unlimited
                        'description' => 'Number of events you can participate in per month (unlimited)',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Event Discount Percent',
                        'slug' => 'event-discount-percent',
                        'limit' => 30,
                        'description' => 'Discount percentage on paid events',
                        'resettable_period' => 0,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Blog Posts',
                        'slug' => 'blog-posts-monthly',
                        'limit' => 0, // 0 = unlimited
                        'description' => 'Number of blog posts you can publish per month (unlimited)',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Lab Hours',
                        'slug' => 'lab-hours-monthly',
                        'limit' => 40,
                        'description' => 'Hours of lab space booking per month (40+ with priority)',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Research Collaborators',
                        'slug' => 'research-collaborators',
                        'limit' => 0, // 0 = unlimited
                        'description' => 'Number of research collaborators allowed (unlimited)',
                        'resettable_period' => 0,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Sample Tracking Limit',
                        'slug' => 'sample-tracking-limit',
                        'limit' => 0, // 0 = unlimited
                        'description' => 'Maximum number of samples to track (unlimited)',
                        'resettable_period' => 0,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Forum Threads',
                        'slug' => 'forum-threads-monthly',
                        'limit' => 0, // Unlimited for all plans
                        'description' => 'Number of forum threads you can create per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                    [
                        'name' => 'Monthly Forum Replies',
                        'slug' => 'forum-replies-monthly',
                        'limit' => 0, // Unlimited for all plans
                        'description' => 'Number of forum replies you can post per month',
                        'resettable_period' => 1,
                        'resettable_interval' => 'month',
                    ],
                ],
            ],
        ];
    }
}
