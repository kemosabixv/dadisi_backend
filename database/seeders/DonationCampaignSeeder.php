<?php

namespace Database\Seeders;

use App\Models\DonationCampaign;
use App\Models\County;
use App\Models\User;
use Illuminate\Database\Seeder;

class DonationCampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get an admin user to be the creator
        $creator = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['super_admin', 'admin', 'content_editor']);
        })->first();

        if (!$creator) {
            $creator = User::first();
        }

        if (!$creator) {
            $this->command->warn('No users found. Skipping DonationCampaignSeeder.');
            return;
        }

        // Get some counties for variety
        $counties = County::take(5)->get();
        $defaultCountyId = $counties->first()?->id;

        $campaigns = [
            [
                'title' => 'Education Fund 2025',
                'description' => '<h2>Supporting Future Scientists</h2>
<p>Help us provide quality STEM education to underprivileged children across Kenya. Your donation will fund:</p>
<ul>
<li>Science laboratory equipment</li>
<li>Textbooks and learning materials</li>
<li>Teacher training programs</li>
<li>Student scholarships</li>
</ul>
<p>Every contribution, no matter how small, makes a difference in a child\'s future.</p>',
                'short_description' => 'Help fund quality STEM education for underprivileged children across Kenya.',
                'goal_amount' => 500000.00,
                'minimum_amount' => 100.00,
                'currency' => 'KES',
                'status' => 'active',
                'county_id' => $defaultCountyId,
                'published_at' => now(),
                'starts_at' => now()->subDays(10),
                'ends_at' => now()->addMonths(3),
            ],
            [
                'title' => 'Community Water Project',
                'description' => '<h2>Clean Water for All</h2>
<p>Access to clean water is a basic human right. This campaign aims to:</p>
<ul>
<li>Build water purification systems in 5 communities</li>
<li>Train local technicians for maintenance</li>
<li>Provide water quality testing equipment</li>
</ul>
<p><strong>Target communities:</strong> Rural areas in Makueni, Kitui, and Machakos counties.</p>',
                'short_description' => 'Building water purification systems for communities without clean water access.',
                'goal_amount' => 750000.00,
                'minimum_amount' => 250.00,
                'currency' => 'KES',
                'status' => 'active',
                'county_id' => $counties->skip(1)->first()?->id ?? $defaultCountyId,
                'published_at' => now()->subDays(5),
                'starts_at' => now()->subDays(5),
                'ends_at' => now()->addMonths(6),
            ],
            [
                'title' => 'Youth Tech Hub',
                'description' => '<h2>Empowering Youth Through Technology</h2>
<p>We\'re establishing a tech hub where young people can learn digital skills including:</p>
<ul>
<li>Web development and programming</li>
<li>Digital marketing and entrepreneurship</li>
<li>Mobile app development</li>
<li>AI and data science fundamentals</li>
</ul>
<p>The hub will provide free access to computers, internet, and mentorship programs.</p>',
                'short_description' => 'Creating a free tech hub for youth to learn programming and digital skills.',
                'goal_amount' => 1000000.00,
                'minimum_amount' => 500.00,
                'currency' => 'KES',
                'status' => 'active',
                'county_id' => $counties->skip(2)->first()?->id ?? $defaultCountyId,
                'published_at' => now()->subDays(3),
                'starts_at' => null,
                'ends_at' => null, // Open-ended campaign
            ],
            [
                'title' => 'Medical Outreach Program',
                'description' => '<h2>Healthcare for Remote Communities</h2>
<p>Many communities lack access to basic healthcare. Our medical outreach program brings doctors and medicine to those who need it most.</p>
<p>Your support helps us:</p>
<ul>
<li>Conduct free medical camps</li>
<li>Provide essential medications</li>
<li>Offer health education workshops</li>
<li>Screen for common diseases</li>
</ul>',
                'short_description' => 'Bringing free medical camps and healthcare to remote communities.',
                'goal_amount' => 300000.00,
                'minimum_amount' => null, // No minimum
                'currency' => 'KES',
                'status' => 'active',
                'county_id' => $counties->skip(3)->first()?->id ?? $defaultCountyId,
                'published_at' => now()->subDays(1),
                'starts_at' => now(),
                'ends_at' => now()->addMonths(2),
            ],
            [
                'title' => 'Environmental Conservation Initiative',
                'description' => '<h2>Protecting Our Natural Heritage</h2>
<p>Kenya\'s biodiversity is under threat. This campaign supports conservation efforts including:</p>
<ul>
<li>Tree planting programs (target: 10,000 trees)</li>
<li>Wildlife habitat restoration</li>
<li>Community awareness campaigns</li>
<li>Sustainable farming training</li>
</ul>
<p>Join us in protecting our environment for future generations.</p>',
                'short_description' => 'Planting 10,000 trees and restoring wildlife habitats across Kenya.',
                'goal_amount' => 200000.00,
                'minimum_amount' => 50.00,
                'currency' => 'KES',
                'status' => 'draft', // Draft campaign for testing
                'county_id' => $counties->skip(4)->first()?->id ?? $defaultCountyId,
                'published_at' => null,
                'starts_at' => now()->addDays(7),
                'ends_at' => now()->addMonths(4),
            ],
            [
                'title' => 'Girls Empowerment Program',
                'description' => '<h2>Empowering Girls Through Education</h2>
<p>This campaign was successfully completed! Thank you to all our donors.</p>
<p>We achieved:</p>
<ul>
<li>100 girls received full scholarships</li>
<li>50 mentorship programs established</li>
<li>3 schools received new facilities</li>
</ul>',
                'short_description' => 'Educational scholarships and mentorship for underprivileged girls.',
                'goal_amount' => 150000.00,
                'minimum_amount' => 100.00,
                'currency' => 'KES',
                'status' => 'completed',
                'county_id' => $defaultCountyId,
                'published_at' => now()->subMonths(6),
                'starts_at' => now()->subMonths(6),
                'ends_at' => now()->subMonths(1),
            ],
        ];

        foreach ($campaigns as $campaignData) {
            $campaignData['created_by'] = $creator->id;
            DonationCampaign::create($campaignData);
        }

        $this->command->info('Created ' . count($campaigns) . ' donation campaigns.');
    }
}
