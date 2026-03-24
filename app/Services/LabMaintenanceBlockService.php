<?php

namespace App\Services;

use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * LabMaintenanceBlockService
 *
 * Handles creation of maintenance blocks, holidays, and closures for lab spaces.
 * Supports bulk creation across multiple slots and days with booking conflict handling.
 */
class LabMaintenanceBlockService
{
    /**
     * Bulk create maintenance blocks for selected slots and days
     *
     * @param LabSpace $space The lab space
     * @param string $blockType Type of block: 'maintenance', 'holiday', 'closure'
     * @param array $selectedDates Array of dates (e.g., ['2026-03-05', '2026-03-06'])
     * @param array $selectedSlots Array of hour slots (e.g., [9, 10, 11]) or null for full day
     * @param string|null $reason Optional reason/description
     * @param int $createdBy User ID who created the block
     * @return array {
     *     'created': int,        // Number of blocks created
     *     'conflicts': int,      // Number of conflicting bookings found
     *     'dates': array         // Details of created blocks
     * }
     */
    public function bulkCreateBlocks(
        LabSpace $space,
        string $blockType,
        array $selectedDates,
        ?array $selectedSlots,
        ?string $reason = null,
        int $createdBy = null
    ): array {
        $createdCount = 0;
        $conflictCount = 0;
        $dateDetails = [];

        foreach ($selectedDates as $dateStr) {
            $date = Carbon::createFromFormat('Y-m-d', $dateStr)->startOfDay();

            // If full day, create single block for entire day
            if (empty($selectedSlots)) {
                $startTime = $date->copy()->setHour($space->opens_at ?? 8)->setMinute(0);
                $endTime = $date->copy()->setHour($space->closes_at ?? 20)->setMinute(0);

                $conflicts = $this->findConflictingBookings($space, $startTime, $endTime);
                $block = $this->createBlock(
                    $space,
                    $startTime,
                    $endTime,
                    $blockType,
                    $reason,
                    $createdBy
                );

                $createdCount++;
                $conflictCount += count($conflicts);
                $dateDetails[] = [
                    'date' => $dateStr,
                    'type' => 'full_day',
                    'block_id' => $block->id,
                    'conflicts' => count($conflicts),
                ];
            } else {
                // Create blocks for each selected hour slot
                foreach ($selectedSlots as $hour) {
                    $startTime = $date->copy()->setHour($hour)->setMinute(0);
                    $endTime = $startTime->copy()->addHour();

                    // Check if slot is within operating hours
                    if (!$this->isWithinOperatingHours($space, $startTime, $endTime)) {
                        continue;
                    }

                    $conflicts = $this->findConflictingBookings($space, $startTime, $endTime);
                    $block = $this->createBlock(
                        $space,
                        $startTime,
                        $endTime,
                        $blockType,
                        $reason,
                        $createdBy
                    );

                    $createdCount++;
                    $conflictCount += count($conflicts);
                    $dateDetails[] = [
                        'date' => $dateStr,
                        'hour' => $hour,
                        'block_id' => $block->id,
                        'conflicts' => count($conflicts),
                    ];
                }
            }
        }

        return [
            'created' => $createdCount,
            'conflicts' => $conflictCount,
            'dates' => $dateDetails,
        ];
    }

    /**
     * Create a single maintenance block
     */
    private function createBlock(
        LabSpace $space,
        Carbon $startTime,
        Carbon $endTime,
        string $blockType,
        ?string $reason = null,
        int $createdBy = null
    ): LabMaintenanceBlock {
        return LabMaintenanceBlock::create([
            'lab_space_id' => $space->id,
            'title' => $this->generateTitle($blockType),
            'reason' => $reason,
            'block_type' => $blockType,
            'starts_at' => $startTime,
            'ends_at' => $endTime,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Generate appropriate title based on block type
     */
    private function generateTitle(string $blockType): string
    {
        return match ($blockType) {
            'holiday' => 'Holiday Closure',
            'closure' => 'Lab Closure',
            'maintenance' => 'Maintenance Window',
            default => 'Scheduled Block',
        };
    }

    /**
     * Check if time slot is within lab operating hours
     */
    private function isWithinOperatingHours(LabSpace $space, Carbon $start, Carbon $end): bool
    {
        $opensAt = $space->opens_at ? intval($space->opens_at) : 8;
        $closesAt = $space->closes_at ? intval($space->closes_at) : 20;

        return $start->hour >= $opensAt && $end->hour <= $closesAt;
    }

    /**
     * Find bookings that conflict with the maintenance block time window
     */
    private function findConflictingBookings(
        LabSpace $space,
        Carbon $startTime,
        Carbon $endTime
    ): Collection {
        return LabBooking::where('lab_space_id', $space->id)
            ->whereIn('status', [
                LabBooking::STATUS_CONFIRMED,
                LabBooking::STATUS_COMPLETED,
            ])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('starts_at', [$startTime, $endTime])
                    ->orWhereBetween('ends_at', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('starts_at', '<', $startTime)
                            ->where('ends_at', '>', $endTime);
                    });
            })
            ->get();
    }

    /**
     * Cancel bookings that conflict with a maintenance block
     * (optional - for handling existing bookings)
     */
    public function cancelConflictingBookings(
        LabSpace $space,
        Carbon $startTime,
        Carbon $endTime,
        string $reason = 'Lab closed for maintenance'
    ): int {
        $bookings = $this->findConflictingBookings($space, $startTime, $endTime);
        $count = 0;

        foreach ($bookings as $booking) {
            $booking->update([
                'status' => LabBooking::STATUS_CANCELLED,
                'rejection_reason' => $reason,
            ]);
            $count++;
        }

        return $count;
    }
}
