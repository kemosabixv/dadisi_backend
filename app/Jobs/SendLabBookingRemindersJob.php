<?php

namespace App\Jobs;

use App\Models\LabBooking;
use App\Services\Contracts\NotificationServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendLabBookingRemindersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     *
     * @param bool $dryRun If true, report what would be sent without sending
     */
    public function __construct(
        protected bool $dryRun = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationServiceContract $notificationService): void
    {
        try {
            // Find confirmed bookings starting between 23 and 25 hours from now (tomorrow)
            $from = now()->addHours(23);
            $to = now()->addHours(25);

            $bookings = LabBooking::where('status', LabBooking::STATUS_CONFIRMED)
                ->whereBetween('starts_at', [$from, $to])
                ->with(['labSpace', 'subscriber'])
                ->lockForUpdate()
                ->get();

            $sentCount = 0;
            $failedCount = 0;

            Log::info('SendLabBookingRemindersJob started', [
                'bookings_count' => $bookings->count(),
                'dry_run' => $this->dryRun,
            ]);

            foreach ($bookings as $booking) {
                try {
                    if (!$this->dryRun) {
                        $notificationService->sendLabBookingReminder($booking);
                        $booking->update(['reminder_sent_at' => now()]);
                    }

                    $sentCount++;

                    Log::info('Lab booking reminder processed', [
                        'booking_id' => $booking->id,
                        'user_id' => $booking->user_id,
                        'dry_run' => $this->dryRun,
                    ]);

                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error('Failed to send lab booking reminder', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('SendLabBookingRemindersJob completed', [
                'sent' => $sentCount,
                'failed' => $failedCount,
                'dry_run' => $this->dryRun,
            ]);

        } catch (\Exception $e) {
            Log::error('SendLabBookingRemindersJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
