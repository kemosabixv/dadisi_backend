<?php

namespace App\Jobs;

use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessEventWaitlistJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 120;

    public function __construct(
        public bool $dryRun = false
    ) {}

    /**
     * Execute the job to process event waitlists and promote users when capacity opens up.
     * 
     * For all published events with capacity limits, promotes waiting users to confirmed
     * registrations as space becomes available.
     */
    public function handle(): void
    {
        Log::info('ProcessEventWaitlistJob started', ['dry_run' => $this->dryRun]);

        try {
            $events = Event::whereNotNull('capacity')
                ->where('status', 'published')
                ->get();

            $totalPromoted = 0;

            foreach ($events as $event) {
                try {
                    if ($this->dryRun) {
                        Log::info('Event waitlist processing skipped (dry-run)', [
                            'event_id' => $event->id,
                            'event_title' => $event->title,
                        ]);
                        continue;
                    }

                    $registrationService = app(\App\Services\Contracts\EventRegistrationServiceContract::class);
                    $promotedCount = $registrationService->promoteWaitlistEntries($event);

                    if ($promotedCount > 0) {
                        $totalPromoted += $promotedCount;
                        Log::info('Event waitlist processed with promotions', [
                            'event_id' => $event->id,
                            'event_title' => $event->title,
                            'promoted_count' => $promotedCount,
                        ]);
                    } else {
                        Log::info('Event waitlist processed with no promotions', [
                            'event_id' => $event->id,
                            'event_title' => $event->title,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to process event waitlist', [
                        'event_id' => $event->id,
                        'event_title' => $event->title,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('ProcessEventWaitlistJob completed', [
                'total_events' => $events->count(),
                'total_promoted' => $totalPromoted,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessEventWaitlistJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
