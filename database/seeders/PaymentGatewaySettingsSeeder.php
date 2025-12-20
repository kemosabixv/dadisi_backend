<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class PaymentGatewaySettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'payment.gateway',
                'value' => 'mock',
                'group' => 'payment',
                'type' => 'string',
                'description' => 'Active payment gateway: mock or pesapal',
                'is_public' => false,
            ],
            [
                'key' => 'payment.mock_success_rate',
                'value' => '100',
                'group' => 'payment',
                'type' => 'integer',
                'description' => 'Success rate percentage for mock payments (0-100)',
                'is_public' => false,
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
