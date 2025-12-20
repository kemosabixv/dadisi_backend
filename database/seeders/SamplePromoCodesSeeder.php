<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\PromoCode;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class SamplePromoCodesSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        
        // Get paid events for promo codes (event_id is required)
        $paidEvents = Event::where('price', '>', 0)
            ->where('status', 'published')
            ->get();

        if ($paidEvents->isEmpty()) {
            $this->command->warn('No paid events found. Skipping promo code seeding.');
            return;
        }

        $totalCodes = 0;

        // Create promo codes for each paid event
        foreach ($paidEvents as $index => $event) {
            $eventCodes = [
                [
                    'code' => 'EARLY' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $event->slug), 0, 6)),
                    'discount_type' => 'percentage',
                    'discount_value' => 15,
                    'usage_limit' => 20,
                    'used_count' => rand(0, 5),
                    'valid_from' => $now->copy()->subWeek(),
                    'valid_until' => $event->starts_at->copy()->subDays(3),
                    'is_active' => true,
                ],
                [
                    'code' => 'VIP' . ($index + 1) . strtoupper(substr($event->slug, 0, 4)),
                    'discount_type' => 'fixed',
                    'discount_value' => 200,
                    'usage_limit' => 10,
                    'used_count' => rand(0, 3),
                    'valid_from' => $now->copy()->subWeek(),
                    'valid_until' => $event->starts_at,
                    'is_active' => true,
                ],
            ];

            foreach ($eventCodes as $codeData) {
                PromoCode::firstOrCreate(
                    ['code' => $codeData['code'], 'event_id' => $event->id],
                    [
                        'discount_type' => $codeData['discount_type'],
                        'discount_value' => $codeData['discount_value'],
                        'usage_limit' => $codeData['usage_limit'],
                        'used_count' => $codeData['used_count'],
                        'valid_from' => $codeData['valid_from'],
                        'valid_until' => $codeData['valid_until'],
                        'is_active' => $codeData['is_active'],
                    ]
                );
                $totalCodes++;
            }
        }

        $this->command->info("Created {$totalCodes} promo codes for " . $paidEvents->count() . " paid events.");
    }
}
