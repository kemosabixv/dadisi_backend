<?php

namespace App\Jobs;

use App\Models\Donation;
use App\Services\Contracts\NotificationServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendDonationRemindersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     *
     * @param int $hours Consider pending donations older than N hours
     * @param bool $dryRun If true, report what would be sent without sending
     */
    public function __construct(
        protected int $hours = 24,
        protected bool $dryRun = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationServiceContract $notificationService): void
    {
        try {
            $threshold = now()->subHours($this->hours);

            $pendingDonations = Donation::where('status', 'pending')
                ->where('created_at', '<=', $threshold)
                ->whereNull('reminder_sent_at')
                ->lockForUpdate()
                ->get();

            $sentCount = 0;
            $failedCount = 0;

            Log::info('SendDonationRemindersJob started', [
                'hours' => $this->hours,
                'donations_count' => $pendingDonations->count(),
                'dry_run' => $this->dryRun,
            ]);

            foreach ($pendingDonations as $donation) {
                try {
                    if (!$this->dryRun) {
                        $notificationService->sendDonationReminder($donation);
                        $donation->update(['reminder_sent_at' => now()]);
                    }

                    $sentCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error('Failed to send donation reminder', [
                        'donation_id' => $donation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('SendDonationRemindersJob completed', [
                'sent' => $sentCount,
                'failed' => $failedCount,
                'dry_run' => $this->dryRun,
            ]);

        } catch (\Exception $e) {
            Log::error('SendDonationRemindersJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
