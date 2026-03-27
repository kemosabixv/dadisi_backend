<?php

namespace App\Jobs;

use App\Models\LabBooking;
use App\Services\Contracts\LabBookingServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class MarkLabNoShowsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes - may process many bookings

    /**
     * Create a new job instance.
     *
     * @param bool $dryRun If true, report what would be marked without marking
     */
    public function __construct(
        protected bool $dryRun = false
    ) {}

    /**
     * Execute the job.
     * 
     * CRITICAL: This job triggers refund processing! Handle with care.
     */
    public function handle(LabBookingServiceContract $bookingService): void
    {
        try {
            // Find bookings that are confirmed, never checked in, and have ended + 15 mins
            // PRD Section 9: 15-minute window after slot end time
            $noShows = LabBooking::where('status', LabBooking::STATUS_CONFIRMED)
                ->whereNull('checked_in_at')
                ->where('ends_at', '<', now()->subMinutes(15))
                ->lockForUpdate() // Lock to prevent race conditions
                ->get();

            $markedCount = 0;
            $failedCount = 0;

            Log::info('MarkLabNoShowsJob started', [
                'dry_run' => $this->dryRun,
                'bookings_to_process' => $noShows->count(),
            ]);

            foreach ($noShows as $booking) {
                try {
                    if (!$this->dryRun) {
                        // CRITICAL: This marks the booking as no-show and triggers refund
                        $bookingService->markNoShow($booking);
                    }

                    $markedCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error('Failed to mark lab booking as no-show', [
                        'booking_id' => $booking->id,
                        'user_id' => $booking->user_id,
                        'error' => $e->getMessage(),
                        'dry_run' => $this->dryRun,
                    ]);
                }
            }

            Log::info('MarkLabNoShowsJob completed', [
                'marked_as_no_show' => $markedCount,
                'failed_to_mark' => $failedCount,
                'dry_run' => $this->dryRun,
            ]);

        } catch (\Exception $e) {
            Log::error('MarkLabNoShowsJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
