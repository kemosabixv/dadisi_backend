<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Models\UserDataRetentionSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupWebhookEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('CleanupWebhookEventsJob: Starting cleanup of old webhook events');

        $retentionDays = UserDataRetentionSetting::getRetentionDays('webhook_events');
        $cutoffDate = now()->subDays($retentionDays);

        $count = WebhookEvent::where('created_at', '<', $cutoffDate)->delete();

        Log::info("CleanupWebhookEventsJob: Purged {$count} old webhook events (Retention limit: {$retentionDays} days)");
    }
}
