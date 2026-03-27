<?php

namespace App\Jobs;

use App\Models\LabBooking;
use App\Models\BookingAuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class MarkLabAttendanceJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 60;

    public function __construct(
        public bool $dryRun = false
    ) {}

    /**
     * Execute the job to finalize lab booking attendance.
     * 
     * Marks confirmed bookings as completed 15 minutes after their end time.
     * This locks bookings against refunds per PRD requirements.
     */
    public function handle(): void
    {
        Log::info('MarkLabAttendanceJob started', ['dry_run' => $this->dryRun]);

        try {
            $cutoff = now()->subMinutes(15);

            // Find bookings that ended more than 15 minutes ago but are still in 'confirmed' status
            // ONLY process those that were checked in (manually or via QR).
            // Bookings without check-in are handled by MarkLabNoShowsJob.
            $bookings = LabBooking::where('status', LabBooking::STATUS_CONFIRMED)
                ->whereNotNull('checked_in_at')
                ->where('ends_at', '<', $cutoff)
                ->lockForUpdate()
                ->get();

            $count = 0;

            foreach ($bookings as $booking) {
                try {
                    if ($this->dryRun) {
                        Log::info('Lab booking marked as completed (dry-run)', [
                            'booking_id' => $booking->id,
                            'user_id' => $booking->user_id,
                        ]);
                        $count++;
                        continue;
                    }

                    // Mark as attended (Completed)
                    // This locks it against refunds as per PRD
                    $booking->update([
                        'status' => LabBooking::STATUS_COMPLETED,
                        'admin_notes' => ($booking->admin_notes ? $booking->admin_notes . "\n" : "") . "Auto-finalized as attended after 15-minute window."
                    ]);

                    BookingAuditLog::create([
                        'booking_id' => $booking->id,
                        'series_id' => $booking->booking_series_id,
                        'action' => 'auto_attended',
                        'notes' => 'Booking automatically marked as attended/completed after 15-minute grace period.'
                    ]);

                    $count++;

                    Log::info('Lab booking marked as attendance completed', [
                        'booking_id' => $booking->id,
                        'user_id' => $booking->user_id,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to mark lab booking attendance', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('MarkLabAttendanceJob completed', [
                'finalized_bookings' => $count,
                'total_found' => $bookings->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('MarkLabAttendanceJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
