<?php

namespace App\Jobs;

use App\Models\EventRegistration;
use App\Models\EventOrder;
use App\Notifications\EventReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendEventRemindersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes

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
    public function handle(): void
    {
        try {
            Log::info('SendEventRemindersJob started', [
                'dry_run' => $this->dryRun,
            ]);

            // Process both registrations and orders
            $sentCount = 0;
            $sentCount += $this->processRegistrations();
            $sentCount += $this->processOrders();

            Log::info('SendEventRemindersJob completed', [
                'sent' => $sentCount,
                'dry_run' => $this->dryRun,
            ]);

        } catch (\Exception $e) {
            Log::error('SendEventRemindersJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Process event registrations (RSVPs)
     */
    protected function processRegistrations(): int
    {
        $sentCount = 0;

        // 24h Reminders for RSVPs
        $registrations24h = EventRegistration::where('status', 'confirmed')
            ->whereNull('reminded_24h_at')
            ->whereHas('event', function ($q) {
                $q->whereBetween('starts_at', [now()->addHours(23), now()->addHours(25)]);
            })
            ->with(['subscriber', 'event'])
            ->lockForUpdate()
            ->get();

        foreach ($registrations24h as $reg) {
            try {
                if (!$this->dryRun) {
                    Notification::send($reg->subscriber, new EventReminder($reg->event, '24h'));
                    $reg->update(['reminded_24h_at' => now()]);
                }
                $sentCount++;
            } catch (\Exception $e) {
                Log::error('Failed to send 24h event reminder', [
                    'registration_id' => $reg->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 1h Reminders for RSVPs
        $registrations1h = EventRegistration::where('status', 'confirmed')
            ->whereNull('reminded_1h_at')
            ->whereHas('event', function ($q) {
                $q->whereBetween('starts_at', [now(), now()->addHours(2)]);
            })
            ->with(['subscriber', 'event'])
            ->lockForUpdate()
            ->get();

        foreach ($registrations1h as $reg) {
            try {
                if (!$this->dryRun) {
                    Notification::send($reg->subscriber, new EventReminder($reg->event, '1h'));
                    $reg->update(['reminded_1h_at' => now()]);
                }
                $sentCount++;
            } catch (\Exception $e) {
                Log::error('Failed to send 1h event reminder', [
                    'registration_id' => $reg->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sentCount;
    }

    /**
     * Process event orders (paid registrations)
     */
    protected function processOrders(): int
    {
        $sentCount = 0;

        // 24h Reminders for Orders
        $orders24h = EventOrder::where('status', 'completed')
            ->whereNull('reminded_24h_at')
            ->whereHas('event', function ($q) {
                $q->whereBetween('starts_at', [now()->addHours(23), now()->addHours(25)]);
            })
            ->with(['subscriber', 'event'])
            ->lockForUpdate()
            ->get();

        foreach ($orders24h as $order) {
            try {
                if (!$this->dryRun) {
                    Notification::send($order->subscriber, new EventReminder($order->event, '24h'));
                    $order->update(['reminded_24h_at' => now()]);
                }
                $sentCount++;
            } catch (\Exception $e) {
                Log::error('Failed to send 24h event reminder (order)', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 1h Reminders for Orders
        $orders1h = EventOrder::where('status', 'completed')
            ->whereNull('reminded_1h_at')
            ->whereHas('event', function ($q) {
                $q->whereBetween('starts_at', [now(), now()->addHours(2)]);
            })
            ->with(['subscriber', 'event'])
            ->lockForUpdate()
            ->get();

        foreach ($orders1h as $order) {
            try {
                if (!$this->dryRun) {
                    Notification::send($order->subscriber, new EventReminder($order->event, '1h'));
                    $order->update(['reminded_1h_at' => now()]);
                }
                $sentCount++;
            } catch (\Exception $e) {
                Log::error('Failed to send 1h event reminder (order)', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sentCount;
    }
}
