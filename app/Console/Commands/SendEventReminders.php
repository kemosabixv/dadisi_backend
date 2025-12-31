<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EventRegistration;
use App\Models\EventOrder;
use App\Notifications\EventReminder;
use Illuminate\Support\Facades\Log;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Send 24h and 1h reminders to event attendees';

    public function handle()
    {
        $this->processRegistrations();
        $this->processOrders();

        $this->info('Event reminders processed.');
        return 0;
    }

    protected function processRegistrations()
    {
        // 24h Reminders for RSVPs
        $registrations24h = EventRegistration::where('status', 'confirmed')
            ->whereNull('reminded_24h_at')
            ->whereHas('event', function ($q) {
                $q->whereBetween('starts_at', [now()->addHours(23), now()->addHours(25)]);
            })->with(['user', 'event'])->get();

        foreach ($registrations24h as $reg) {
            $this->sendReminder($reg, '24h');
        }

        // 1h Reminders for RSVPs
        $registrations1h = EventRegistration::where('status', 'confirmed')
            ->whereNull('reminded_1h_at')
            ->whereHas('event', function ($q) {
                $q->whereBetween('starts_at', [now(), now()->addHours(2)]);
            })->with(['user', 'event'])->get();

        foreach ($registrations1h as $reg) {
            $this->sendReminder($reg, '1h');
        }
    }

    protected function processOrders()
    {
        // 24h Reminders for Paid Orders
        $orders24h = EventOrder::where('status', 'paid')
            ->whereNull('reminded_24h_at')
            ->whereHas('event', function ($q) {
                $q->whereBetween('starts_at', [now()->addHours(23), now()->addHours(25)]);
            })->with(['user', 'event'])->get();

        foreach ($orders24h as $order) {
            $this->sendReminder($order, '24h');
        }

        // 1h Reminders for Paid Orders
        $orders1h = EventOrder::where('status', 'paid')
            ->whereNull('reminded_1h_at')
            ->whereHas('event', function ($q) {
                $q->whereBetween('starts_at', [now(), now()->addHours(2)]);
            })->with(['user', 'event'])->get();

        foreach ($orders1h as $order) {
            $this->sendReminder($order, '1h');
        }
    }

    protected function sendReminder($notifiable_record, $type)
    {
        $user = $notifiable_record->user;
        $event = $notifiable_record->event;

        if (!$user) {
            // For guest orders, we might want to send an email directly, but the EventReminder notification
            // is designed for notifiable objects (usually Users). 
            // Currently, only logged-in users get in-app notifications.
            // If it's a guest order, we'll skip for now or we could use Mail::to($order->guest_email).
            return;
        }

        try {
            $user->notify(new EventReminder($event, $type));
            
            $column = "reminded_{$type}_at";
            $notifiable_record->update([$column => now()]);
            
            $this->info("Sent {$type} reminder to {$user->email} for event: {$event->title}");
        } catch (\Exception $e) {
            Log::error("Failed to send event reminder", [
                'user_id' => $user->id,
                'event_id' => $event->id,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }
}
