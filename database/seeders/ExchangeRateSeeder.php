<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExchangeRate;

class ExchangeRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ExchangeRate::firstOrCreate([
            'from_currency' => 'USD',
            'to_currency' => 'KES',
        ], [
            'rate' => 145.0,
            'inverse_rate' => round(1 / 145.0, 6),
            'cache_minutes' => 10080, // 7 days in minutes
            'last_updated' => now()->subDays(1), // Mark as needing refresh soon
        ]);

        $this->command->info('Exchange rates seeded successfully!');
        $this->command->info('- USD to KES: 145.00 (default rate)');
        $this->command->info('- KES to USD: 0.006897');
        $this->command->info('- Cache: 7 days (10080 minutes)');
    }
}
