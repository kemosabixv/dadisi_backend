<?php

namespace Database\Seeders;

use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class LabMaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        $labs = LabSpace::all();
        if ($labs->isEmpty()) {
            return;
        }

        $admin = User::whereHas('roles', fn($q) => $q->where('name', 'super_admin'))->first();
        $now = Carbon::now();

        foreach ($labs as $lab) {
            // 1. One-off maintenance in the past
            LabMaintenanceBlock::create([
                'lab_space_id' => $lab->id,
                'title' => 'Routine Equipment Check',
                'reason' => 'Annual calibration of lab instruments',
                'block_type' => LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
                'starts_at' => $now->copy()->subDays(10)->setTime(8, 0),
                'ends_at' => $now->copy()->subDays(10)->setTime(12, 0),
                'recurring' => false,
                'created_by' => $admin?->id,
            ]);

            // 2. Ongoing maintenance (present) for one of the labs
            if ($lab->slug === 'wet-lab') {
                LabMaintenanceBlock::create([
                    'lab_space_id' => $lab->id,
                    'title' => 'Deep Cleaning',
                    'reason' => 'Quarterly sterilization of biotech benches',
                    'block_type' => LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
                    'starts_at' => $now->copy()->subHours(2),
                    'ends_at' => $now->copy()->addHours(6),
                    'recurring' => false,
                    'created_by' => $admin?->id,
                ]);
            }

            // 3. Future holiday closure
            LabMaintenanceBlock::create([
                'lab_space_id' => $lab->id,
                'title' => 'Public Holiday Closure',
                'reason' => 'Lab closed for national holiday',
                'block_type' => LabMaintenanceBlock::BLOCK_TYPE_HOLIDAY,
                'starts_at' => $now->copy()->addDays(15)->setTime(0, 0),
                'ends_at' => $now->copy()->addDays(15)->setTime(23, 59),
                'recurring' => false,
                'created_by' => $admin?->id,
            ]);

            // 4. Future scheduled maintenance
            LabMaintenanceBlock::create([
                'lab_space_id' => $lab->id,
                'title' => 'HVAC System Maintenance',
                'reason' => 'Scheduled air filtration system replacement',
                'block_type' => LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
                'starts_at' => $now->copy()->addDays(20)->setTime(9, 0),
                'ends_at' => $now->copy()->addDays(20)->setTime(17, 0),
                'recurring' => false,
                'created_by' => $admin?->id,
            ]);
        }

        $this->command->info('Lab maintenance blocks seeded successfully.');
    }
}
