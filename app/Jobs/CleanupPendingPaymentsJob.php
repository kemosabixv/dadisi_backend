<?php

namespace App\Jobs;

use App\Models\PendingPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupPendingPaymentsJob implements ShouldQueue
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
        Log::info('CleanupPendingPaymentsJob: Starting cleanup of stale pending payments');

        // Find payments that are pending and expired
        $retentionMinutes = \App\Models\UserDataRetentionSetting::getRetentionMinutes('pending_payments');
        
        $stalePayments = PendingPayment::where('status', 'pending')
            ->where(function ($query) use ($retentionMinutes) {
                // Either explicit expires_at has passed,
                // or it's older than the configured retention period
                $query->where('expires_at', '<', now())
                      ->orWhere('created_at', '<', now()->subMinutes($retentionMinutes));
            })
            ->get();

        $count = 0;
        foreach ($stalePayments as $payment) {
            $payment->markExpired();
            $count++;
        }

        Log::info("CleanupPendingPaymentsJob: Expired {$count} stale payments");
    }
}
