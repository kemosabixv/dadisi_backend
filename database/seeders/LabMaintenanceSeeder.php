<?php

namespace Database\Seeders;

use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use App\Models\User;
use App\Services\LabMaintenanceBlockService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class LabMaintenanceSeeder extends Seeder
{
    public function run(LabMaintenanceBlockService $service): void
    {
        // We no longer truncate to remain idempotent and preserve manual user data.
        // LabMaintenanceBlock::query()->delete(); 

        $labs = LabSpace::all();
        if ($labs->isEmpty()) {
            return;
        }

        $admin = User::whereHas('roles', fn($q) => $q->where('name', 'super_admin'))->first();
        if (!$admin) {
             $admin = User::first(); // Fallback if no specific role found
        }
        $now = Carbon::now();

        foreach ($labs as $lab) {
            // Only seed one-offs for specific labs to avoid clutter
            if (in_array($lab->slug, ['electronics-lab', 'wet-lab'])) {
                // 1. Past Maintenance (Completed)
                LabMaintenanceBlock::updateOrCreate(
                    [
                        'lab_space_id' => $lab->id,
                        'title' => 'Routine Equipment Check',
                    ],
                    [
                        'reason' => 'Annual calibration of lab instruments',
                        'block_type' => LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
                        'status' => LabMaintenanceBlock::STATUS_COMPLETED,
                        'completion_report' => 'All multimeters and oscilloscopes calibrated. No issues found.',
                        'starts_at' => $now->copy()->subDays(10)->setTime(8, 0),
                        'ends_at' => $now->copy()->subDays(10)->setTime(12, 0),
                        'recurring' => false,
                        'created_by' => $admin?->id,
                    ]
                );

                // 2. Future holiday (One-off Full Day)
                LabMaintenanceBlock::updateOrCreate(
                    [
                        'lab_space_id' => $lab->id,
                        'title' => 'Upcoming Public Holiday',
                    ],
                    [
                        'reason' => 'The lab will be closed for the national day',
                        'block_type' => LabMaintenanceBlock::BLOCK_TYPE_HOLIDAY,
                        'status' => LabMaintenanceBlock::STATUS_SCHEDULED,
                        'starts_at' => $now->copy()->addDays(5)->startOfDay(),
                        'ends_at' => $now->copy()->addDays(5)->endOfDay(),
                        'recurring' => false,
                        'created_by' => $admin?->id,
                    ]
                );
            }

            // 3. Weekly Maintenance Series (Master Block for Electronics Lab)
            if ($lab->slug === 'electronics-lab') {
                // For series, we check if master already exists
                $exists = LabMaintenanceBlock::where('lab_space_id', $lab->id)
                    ->where('title', 'Weekly Bench Optimization (Series)')
                    ->exists();

                if (!$exists) {
                    $service->createMaintenanceSeries(
                        $lab,
                        LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
                        [
                            'freq' => 'weekly',
                            'interval' => 1,
                            'seeds' => [
                                ['point' => 'MO', 'full_day' => false, 'start' => '08:00', 'end' => '10:00'],
                                ['point' => 'WE', 'full_day' => true] // Full day for deep cleaning on Wed
                            ],
                            'until' => $now->copy()->addMonths(1)->format('Y-m-d')
                        ],
                        'Fine-tuning soldering stations and oscilloscope calibration',
                        'Weekly Bench Optimization',
                        $admin?->id
                    );
                }
            }

            // 4. Monthly Deep Cleaning (Master Block for Wet Lab)
            if ($lab->slug === 'wet-lab') {
                $exists = LabMaintenanceBlock::where('lab_space_id', $lab->id)
                    ->where('title', 'Monthly Sterilization (Series)')
                    ->exists();

                if (!$exists) {
                    $service->createMaintenanceSeries(
                        $lab,
                        LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
                        [
                            'freq' => 'monthly',
                            'interval' => 1,
                            'seeds' => [
                                ['point' => '15', 'full_day' => false, 'start' => '14:00', 'end' => '18:00']
                            ],
                            'until' => $now->copy()->addYear()->format('Y-m-d')
                        ],
                        'Total sterilization of all surfaces and disposal of hazardous waste',
                        'Monthly Sterilization',
                        $admin?->id
                    );
                }
            }
        }

        $this->command->info('Lab maintenance blocks seeded successfully (with idempotence).');
    }
}
