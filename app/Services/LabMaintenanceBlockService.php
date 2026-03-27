<?php

namespace App\Services;

use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use App\Models\MaintenanceBlockRollover;
use App\Services\Contracts\LabBookingServiceContract;
use App\Services\LabManagement\SeriesExpansionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * LabMaintenanceBlockService
 *
 * Handles creation of maintenance blocks, holidays, and closures for lab spaces.
 * Supports bulk creation across multiple slots and days with booking conflict handling.
 */
class LabMaintenanceBlockService
{
    protected $expansionService;

    protected $bookingService;

    public function __construct(
        SeriesExpansionService $expansionService,
        LabBookingServiceContract $bookingService
    ) {
        $this->expansionService = $expansionService;
        $this->bookingService = $bookingService;
    }

    /**
     * Bulk create maintenance blocks for selected slots and days
     *
     * @param  LabSpace  $space  The lab space
     * @param  string  $blockType  Type of block: 'maintenance', 'holiday', 'closure'
     * @param  array  $selectedDates  Array of dates (e.g., ['2026-03-05', '2026-03-06'])
     * @param  array  $selectedSlots  Array of hour slots (e.g., [9, 10, 11]) or null for full day
     * @param  string|null  $reason  Optional reason/description
     * @param  int  $createdBy  User ID who created the block
     * @return array {
     *               'created': int,        // Number of blocks created
     *               'conflicts': int,      // Number of conflicting bookings found
     *               'dates': array         // Details of created blocks
     *               }
     */
    public function bulkCreateBlocks(
        LabSpace $space,
        string $blockType,
        array $selectedDates,
        ?array $selectedSlots,
        ?string $reason = null,
        ?int $createdBy = null
    ): array {
        $createdCount = 0;
        $conflictCount = 0;
        $dateDetails = [];

        foreach ($selectedDates as $dateStr) {
            $date = Carbon::createFromFormat('Y-m-d', $dateStr)->startOfDay();

            // If full day, create single block for entire day
            if (empty($selectedSlots)) {
                $startTime = $date->copy()->startOfDay();
                $endTime = $date->copy()->endOfDay();

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

                    // Note: We allow maintenance blocks outside operating hours by default
                    // as maintenance often happens when the lab is closed.

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
        ?int $createdBy = null
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
     * Create a maintenance series from a recurrence rule.
     */
    public function createMaintenanceSeries(
        LabSpace $space,
        string $blockType,
        array $rule,
        ?string $reason = null,
        ?string $title = null,
        ?int $createdBy = null
    ): array {
        return DB::transaction(function () use ($space, $blockType, $rule, $reason, $title, $createdBy) {
            $dtStart = isset($rule['dt_start']) ? Carbon::parse($rule['dt_start']) : now();

            // 1. Expand rule to candidate instances
            $candidateWindows = $this->expansionService->expandRule($rule, $dtStart);

            if ($candidateWindows->isEmpty()) {
                throw new \Exception('The recurrence rule did not generate any valid dates.');
            }

            // 2. Create Parent Template Block
            $parentBlock = LabMaintenanceBlock::create([
                'lab_space_id' => $space->id,
                'title' => $title ?: ($this->generateTitle($blockType).' (Series)'),
                'reason' => $reason,
                'block_type' => $blockType,
                'starts_at' => $candidateWindows->first()['starts_at'],
                'ends_at' => $candidateWindows->last()['ends_at'],
                'recurring' => true,
                'recurrence_rule' => $rule,
                'created_by' => $createdBy,
            ]);

            // 3. Create Child Instances
            $instances = collect();
            foreach ($candidateWindows as $window) {
                // Skip if date is a holiday/closure (Skip logic)
                $isClosed = LabMaintenanceBlock::where('lab_space_id', $space->id)
                    ->where('starts_at', '<', $window['ends_at'])
                    ->where('ends_at', '>', $window['starts_at'])
                    ->whereIn('block_type', ['holiday', 'closure'])
                    ->exists();

                if ($isClosed && ! in_array($blockType, ['holiday', 'closure'])) {
                    continue; // Skip this maintenance instance
                }

                $instance = LabMaintenanceBlock::create([
                    'lab_space_id' => $space->id,
                    'recurrence_parent_id' => $parentBlock->id,
                    'title' => $title ?: $this->generateTitle($blockType),
                    'reason' => $reason,
                    'block_type' => $blockType,
                    'starts_at' => $window['starts_at'],
                    'ends_at' => $window['ends_at'],
                    'recurring' => false, // Individual instances aren't "recurring" on their own
                    'created_by' => $createdBy,
                ]);
                $instances->push($instance);
            }

            // 4. Process Batch Rollovers for the whole series
            $rolloverResults = $this->processSeriesRollovers($instances, $reason ?? 'Scheduled maintenance series');

            return [
                'series_id' => $parentBlock->id,
                'instances_created' => $instances->count(),
                'conflicts_identified' => $rolloverResults['total'],
                'results' => $rolloverResults['results'],
            ];
        });
    }

    /**
     * Process rollovers for a collection of maintenance blocks (a series).
     */
    public function processSeriesRollovers(Collection $blocks, string $reason): array
    {
        if ($blocks->isEmpty()) {
            return ['total' => 0, 'results' => ['auto_moved' => 0, 'pending_resolution' => 0]];
        }

        $spaceId = $blocks->first()->lab_space_id;
        $seriesId = $blocks->first()->recurrence_parent_id;

        // Find all unique conflicting bookings for the entire series range
        $conflictingBookings = LabBooking::where('lab_space_id', $spaceId)
            ->whereIn('status', [LabBooking::STATUS_CONFIRMED])
            ->where(function ($query) use ($blocks) {
                foreach ($blocks as $block) {
                    $query->orWhere(function ($q) use ($block) {
                        $q->where('starts_at', '<', $block->ends_at)
                            ->where('ends_at', '>', $block->starts_at);
                    });
                }
            })
            ->get();

        $results = [
            'auto_moved' => 0,
            'pending_resolution' => 0,
        ];

        foreach ($conflictingBookings as $booking) {
            // Attempt to find an alternative slot avoiding the ENTIRE series
            $newSlot = $this->bookingService->findAlternativeSlot(
                $spaceId,
                $booking->duration_hours,
                $blocks,
                $booking->id
            );

            if ($newSlot) {
                // Auto-move
                $originalStartsAt = $booking->starts_at;
                $originalEndsAt = $booking->ends_at;

                $booking->update([
                    'starts_at' => $newSlot['starts_at'],
                    'ends_at' => $newSlot['ends_at'],
                    'status' => LabBooking::STATUS_ROLLED_OVER,
                ]);

                MaintenanceBlockRollover::create([
                    'maintenance_block_id' => $blocks->first(fn ($b) => $b->starts_at < $originalEndsAt && $b->ends_at > $originalStartsAt
                    )->id,
                    'series_id' => $seriesId,
                    'original_booking_id' => $booking->id,
                    'rolled_over_booking_id' => $booking->id,
                    'original_booking_data' => [
                        'starts_at' => $originalStartsAt,
                        'ends_at' => $originalEndsAt,
                    ],
                    'status' => MaintenanceBlockRollover::STATUS_ROLLED_OVER,
                    'notes' => "Auto-moved: {$reason}",
                ]);

                $results['auto_moved']++;
            } else {
                // Flag for resolution
                $booking->update([
                    'status' => LabBooking::STATUS_PENDING_USER_RESOLUTION,
                ]);

                MaintenanceBlockRollover::create([
                    'maintenance_block_id' => $blocks->first(fn ($b) => $b->starts_at < $booking->ends_at && $b->ends_at > $booking->starts_at
                    )->id,
                    'series_id' => $seriesId,
                    'original_booking_id' => $booking->id,
                    'status' => MaintenanceBlockRollover::STATUS_PENDING_USER,
                    'notes' => "Conflict identified: {$reason}",
                ]);

                $results['pending_resolution']++;
            }
        }

        return [
            'total' => $conflictingBookings->count(),
            'results' => $results,
        ];
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

    /**
     * Update an entire maintenance series.
     */
    public function updateMaintenanceSeries(
        int $masterBlockId,
        string $blockType,
        array $rule,
        ?string $reason = null,
        ?int $updatedBy = null
    ): array {
        return DB::transaction(function () use ($masterBlockId, $blockType, $rule, $reason, $updatedBy) {
            $masterBlock = LabMaintenanceBlock::findOrFail($masterBlockId);
            
            // 0. If master is cancelled or completed, prevent technical edits
            if (in_array($masterBlock->status, [LabMaintenanceBlock::STATUS_COMPLETED, LabMaintenanceBlock::STATUS_CANCELLED])) {
                throw new \Exception("Cannot update a series that is already {$masterBlock->status}.");
            }

            // 1. Delete only scheduled future child instances (preserve completed ones for history)
            $masterBlock->instances()
                ->where('status', LabMaintenanceBlock::STATUS_SCHEDULED)
                ->where('starts_at', '>=', now())
                ->delete();

            // 2. Expand new rule
            $dtStart = isset($rule['dt_start']) ? Carbon::parse($rule['dt_start']) : now();
            $candidateWindows = $this->expansionService->expandRule($rule, $dtStart);

            if ($candidateWindows->isEmpty()) {
                throw new \Exception('The updated recurrence rule did not generate any valid dates.');
            }

            // 3. Update Master Block
            $masterBlock->update([
                'title' => $this->generateTitle($blockType).' (Series)',
                'reason' => $reason,
                'block_type' => $blockType,
                'starts_at' => $candidateWindows->first()['starts_at'],
                'ends_at' => $candidateWindows->last()['ends_at'],
                'recurrence_rule' => $rule,
            ]);

            // 4. Re-create Child Instances
            $instances = collect();
            foreach ($candidateWindows as $window) {
                // Skip if date is a holiday/closure
                $isClosed = LabMaintenanceBlock::where('lab_space_id', $masterBlock->lab_space_id)
                    ->where('starts_at', '<', $window['ends_at'])
                    ->where('ends_at', '>', $window['starts_at'])
                    ->whereIn('block_type', ['holiday', 'closure'])
                    ->where('id', '!=', $masterBlock->id)
                    ->exists();

                if ($isClosed && ! in_array($blockType, ['holiday', 'closure'])) {
                    continue; 
                }

                $instance = LabMaintenanceBlock::create([
                    'lab_space_id' => $masterBlock->lab_space_id,
                    'recurrence_parent_id' => $masterBlock->id,
                    'title' => $this->generateTitle($blockType),
                    'reason' => $reason,
                    'block_type' => $blockType,
                    'starts_at' => $window['starts_at'],
                    'ends_at' => $window['ends_at'],
                    'recurring' => false,
                    'created_by' => $updatedBy ?? $masterBlock->created_by,
                ]);
                $instances->push($instance);
            }

            // 5. Re-process Batch Rollovers
            $rolloverResults = $this->processSeriesRollovers($instances, $reason ?? 'Updated maintenance series');

            return [
                'master' => $masterBlock,
                'instances_created' => $instances->count(),
                'rollovers' => $rolloverResults,
            ];
        });
    }

    /**
     * Mark a maintenance block as completed with a report.
     */
    public function completeMaintenance(int $id, string $report, ?int $updatedBy = null): LabMaintenanceBlock
    {
        $block = LabMaintenanceBlock::findOrFail($id);
        
        $block->update([
            'status' => LabMaintenanceBlock::STATUS_COMPLETED,
            'completion_report' => $report,
        ]);

        return $block;
    }

    /**
     * Cancel a maintenance block or series.
     */
    public function cancelMaintenance(int $id, bool $allSeries = false): array
    {
        $block = LabMaintenanceBlock::findOrFail($id);

        if ($allSeries && $block->recurring) {
            // Cancel master and all future scheduled instances
            $block->update(['status' => LabMaintenanceBlock::STATUS_CANCELLED]);
            $count = $block->instances()
                ->where('status', LabMaintenanceBlock::STATUS_SCHEDULED)
                ->where('starts_at', '>=', now())
                ->update(['status' => LabMaintenanceBlock::STATUS_CANCELLED]);
            
            // Note: In a real system, we might want to "restore" rolled-over bookings here.
            // For now, we just update the status.

            return ['cancelled_count' => $count + 1];
        }

        $block->update(['status' => LabMaintenanceBlock::STATUS_CANCELLED]);
        return ['cancelled_count' => 1];
    }

    /**
     * Delete an entire maintenance series atomically.
     *
     * @param int|string $seriesId
     * @return bool
     */
    public function deleteMaintenanceSeries(int|string $seriesId): bool
    {
        return DB::transaction(function () use ($seriesId) {
            // Delete all blocks in the series (including master if series_id is master id)
            LabMaintenanceBlock::where('recurrence_parent_id', $seriesId)
                ->orWhere('id', $seriesId)
                ->delete();
            
            return true;
        });
    }
}


