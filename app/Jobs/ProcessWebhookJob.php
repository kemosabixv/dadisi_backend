<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\PesapalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $webhookEventId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PesapalService $pesapalService): void
    {
        $webhookEvent = WebhookEvent::find($this->webhookEventId);

        if (!$webhookEvent) {
            Log::error('ProcessWebhookJob: Webhook event not found', ['id' => $this->webhookEventId]);
            return;
        }

        try {
            $payload = $webhookEvent->payload;
            $provider = $webhookEvent->provider;

            Log::info("ProcessWebhookJob: Processing {$provider} webhook", [
                'id' => $webhookEvent->id,
                'external_id' => $webhookEvent->external_id
            ]);

            if ($provider === 'pesapal') {
                $result = $pesapalService->processWebhook($payload);
                
                $webhookEvent->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'error' => $result['status'] === 'payment_not_found' ? 'Payment not found' : null,
                ]);
            } elseif ($provider === 'mock') {
                $result = \App\Services\MockPaymentService::processGenericWebhook($payload);
                
                $webhookEvent->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'error' => $result['status'] === 'payment_not_found' ? 'Payment not found' : null,
                ]);
            } else {
                // Handle other providers (like 'mock' if we categorize it separately)
                $webhookEvent->update([
                    'status' => 'ignored',
                    'error' => 'Unsupported provider',
                    'processed_at' => now(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('ProcessWebhookJob: Failed to process webhook', [
                'id' => $webhookEvent->id,
                'error' => $e->getMessage()
            ]);

            $webhookEvent->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
}
