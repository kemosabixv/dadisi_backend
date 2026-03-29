<?php

namespace App\Console\Commands;

use App\Models\SubscriptionEnhancement;
use App\Services\Payments\MockPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessMockRenewals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mock:process-renewals {--dry-run : Only list subscriptions that would be processed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate recurring billing webhooks for mock subscriptions (Staging only)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (app()->isProduction()) {
            $this->error('CRITICAL: This command is for mock testing and CANNOT be run in production.');
            return 1;
        }

        $dueSubscriptions = SubscriptionEnhancement::where('status', 'active')
            ->where('pesapal_recurring_enabled', true)
            ->where(function ($query) {
                $query->whereNull('next_auto_renewal_at')
                    ->orWhere('next_auto_renewal_at', '<=', now());
            })
            ->whereHas('subscription.payments', function ($query) {
                $query->where('gateway', 'mock_pesapal');
            })
            ->with(['subscription.user', 'subscription.payments'])
            ->get();

        if ($dueSubscriptions->isEmpty()) {
            $this->info('No mock subscriptions due for renewal.');
            return 0;
        }

        $this->info("Found {$dueSubscriptions->count()} subscriptions due for mock renewal.");

        foreach ($dueSubscriptions as $enhancement) {
            $subscription = $enhancement->subscription;
            $user = $subscription?->user;

            if (!$subscription || !$user) {
                $this->warn("Skipping enhancement #{$enhancement->id}: Missing subscription or user relation.");
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("Dry-run: Would process renewal for User: {$user->email}, Plan ID: {$enhancement->plan_id}");
                continue;
            }

            $this->info("Simulating renewal for {$user->email}...");

            // Determine if this should be a success or failure based on phone number
            // (MockPaymentService uses phone patterns)
            $phone = $user->phone ?? '254701234567'; // Default success
            
            // Generate a mock tracking ID
            $orderTrackingId = 'MOCK_RECURRING_' . \Illuminate\Support\Str::random(16);

            // Prepare payload for MockPaymentService::processGenericWebhook
            // We simulate the structure that handleWebhook would pass
            $payload = [
                'transaction_id' => 'RECURRING_' . \Illuminate\Support\Str::random(12), // Simulated recurring transaction
                'order_tracking_id' => $enhancement->pesapal_order_tracking_id ?? $orderTrackingId,
                'status' => MockPaymentService::isFailurePhone($phone) ? 'failed' : 'completed',
                'notification_type' => 'RECURRING',
                'merchant_reference' => $enhancement->subscription_id,
            ];

            try {
                $result = MockPaymentService::processGenericWebhook($payload);
                
                if ($result['status'] === 'processed') {
                    $this->info("Successfully processed renewal for {$user->email}. New status: {$result['new_status']}");
                    
                    // Update the enhancement tracking if successful
                    if ($result['new_status'] === 'paid') {
                        $enhancement->update([
                            'last_pesapal_recurring_at' => now(),
                            'last_renewal_result' => 'success',
                        ]);
                        // Note: The actual next renewal date is usually updated by SubscriptionCoreService::activate
                        // which is called inside activatePayable in MockPaymentService.
                    }
                } else {
                    $this->warn("Failed to process renewal for {$user->email}: {$result['status']}");
                }
            } catch (\Exception $e) {
                $this->error("Error processing renewal for {$user->email}: " . $e->getMessage());
                Log::error('Mock renewal command error', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return 0;
    }
}
