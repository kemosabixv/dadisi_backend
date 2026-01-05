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
            [
                'key' => 'pesapal.environment',
                'value' => 'sandbox',
                'group' => 'pesapal',
                'type' => 'string',
                'description' => 'Pesapal environment: sandbox or live',
                'is_public' => false,
            ],
            [
                'key' => 'pesapal.consumer_key',
                'value' => '',
                'group' => 'pesapal',
                'type' => 'string',
                'description' => 'Pesapal API Consumer Key',
                'is_public' => false,
            ],
            [
                'key' => 'pesapal.consumer_secret',
                'value' => '',
                'group' => 'pesapal',
                'type' => 'string',
                'description' => 'Pesapal API Consumer Secret',
                'is_public' => false,
            ],
            [
                'key' => 'pesapal.webhook_url',
                'value' => config('app.url').'/api/webhooks/pesapal',
                'group' => 'pesapal',
                'type' => 'string',
                'description' => 'Base URL for Pesapal IPN notifications',
                'is_public' => false,
            ],
            [
                'key' => 'pesapal.webhook_secret',
                'value' => 'dadisi_sec_pk_3986_v3',
                'group' => 'pesapal',
                'type' => 'string',
                'description' => 'Secret token appended to webhooks for security',
                'is_public' => false,
            ],
            [
                'key' => 'pesapal.callback_url',
                'value' => config('app.url').'/payment/callback',
                'group' => 'pesapal',
                'type' => 'string',
                'description' => 'Backend landing URL after payment (redirects to frontend)',
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
