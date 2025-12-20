<?php

namespace Database\Seeders;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\County;
use App\Models\User;
use Illuminate\Database\Seeder;

class DonationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get counties for random assignment
        $counties = County::pluck('id')->toArray();
        if (empty($counties)) {
            $this->command->warn('No counties found. Run CountiesTableSeeder first.');
            return;
        }

        // Get some users for registered donations
        $users = User::take(10)->pluck('id')->toArray();

        // Get campaigns for campaign-linked donations
        $campaigns = DonationCampaign::where('status', 'active')->get();

        // Sample Kenyan names
        $firstNames = ['James', 'Mary', 'John', 'Grace', 'Peter', 'Faith', 'David', 'Sarah', 'Michael', 'Ann', 'Daniel', 'Jane', 'Joseph', 'Rose', 'Samuel', 'Mercy', 'Brian', 'Joy', 'Kevin', 'Hope'];
        $lastNames = ['Mwangi', 'Ochieng', 'Wanjiku', 'Kamau', 'Otieno', 'Njeri', 'Kibet', 'Auma', 'Kipchoge', 'Wambui', 'Omondi', 'Nyambura', 'Mutua', 'Adhiambo', 'Kimani', 'Chebet', 'Odinga', 'Muthoni', 'Korir', 'Atieno'];

        $donations = [];

        // Generate campaign donations
        foreach ($campaigns as $campaign) {
            $donationCount = rand(5, 15);
            
            for ($i = 0; $i < $donationCount; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $isRegistered = rand(0, 1) && !empty($users);
                $status = $this->getRandomStatus();

                $donations[] = [
                    'user_id' => $isRegistered ? $users[array_rand($users)] : null,
                    'donor_name' => $firstName . ' ' . $lastName,
                    'donor_email' => strtolower($firstName) . '.' . strtolower($lastName) . rand(1, 99) . '@example.com',
                    'donor_phone' => '+2547' . rand(10000000, 99999999),
                    'county_id' => $campaign->county_id ?? $counties[array_rand($counties)],
                    'amount' => $this->getRandomAmount($campaign->minimum_amount),
                    'currency' => $campaign->currency ?? 'KES',
                    'status' => $status,
                    'campaign_id' => $campaign->id,
                    'notes' => rand(0, 1) ? $this->getRandomMessage() : null,
                    'created_at' => now()->subDays(rand(1, 30)),
                    'updated_at' => now()->subDays(rand(0, 10)),
                ];
            }
        }

        // Generate general fund donations (no campaign)
        for ($i = 0; $i < 20; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $isRegistered = rand(0, 1) && !empty($users);
            $status = $this->getRandomStatus();

            $donations[] = [
                'user_id' => $isRegistered ? $users[array_rand($users)] : null,
                'donor_name' => $firstName . ' ' . $lastName,
                'donor_email' => strtolower($firstName) . '.' . strtolower($lastName) . rand(1, 99) . '@example.com',
                'donor_phone' => '+2547' . rand(10000000, 99999999),
                'county_id' => $counties[array_rand($counties)],
                'amount' => $this->getRandomAmount(100),
                'currency' => 'KES',
                'status' => $status,
                'campaign_id' => null,
                'notes' => rand(0, 1) ? $this->getRandomMessage() : null,
                'created_at' => now()->subDays(rand(1, 60)),
                'updated_at' => now()->subDays(rand(0, 10)),
            ];
        }

        foreach ($donations as $donationData) {
            Donation::create($donationData);
        }

        $this->command->info('Created ' . count($donations) . ' sample donations.');
    }

    /**
     * Get random donation amount
     */
    private function getRandomAmount(?float $minimum = 100): float
    {
        $min = max(100, $minimum ?? 100);
        $amounts = [
            $min,
            $min * 2,
            500,
            1000,
            2000,
            2500,
            5000,
            10000,
            25000,
            50000,
        ];
        return (float) $amounts[array_rand($amounts)];
    }

    /**
     * Get random status with weighted distribution
     */
    private function getRandomStatus(): string
    {
        $rand = rand(1, 100);
        if ($rand <= 70) return 'paid';      // 70% paid
        if ($rand <= 85) return 'pending';   // 15% pending
        if ($rand <= 95) return 'failed';    // 10% failed
        return 'refunded';                    // 5% refunded
    }

    /**
     * Get random donation message
     */
    private function getRandomMessage(): string
    {
        $messages = [
            'Keep up the great work!',
            'Happy to support this cause.',
            'In memory of my late grandfather.',
            'For the children.',
            'God bless this initiative.',
            'Wishing you success in this project.',
            'A small contribution for a big cause.',
            'Thank you for making a difference.',
            'Proud to be part of this community.',
            'May this help those in need.',
        ];
        return $messages[array_rand($messages)];
    }
}
