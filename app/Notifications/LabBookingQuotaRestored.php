<?php

namespace App\Notifications;

use App\Channels\SupabaseChannel;
use App\Models\LabBooking;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to users when their lab booking quota has been restored.
 */
class LabBookingQuotaRestored extends Notification
{
    public function __construct(
        protected LabBooking $booking,
        protected float $hoursRestored
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', SupabaseChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $space = $this->booking->labSpace;
        
        return (new MailMessage)
            ->subject('Lab Quota Restored')
            ->greeting('Hello ' . $this->booking->payer_name . ',')
            ->line("Good news! Your lab quota has been restored following the cancellation of your booking.")
            ->line("**Lab Space:** {$space->name}")
            ->line("**Date:** " . $this->booking->starts_at->format('l, F j, Y'))
            ->line("**Hours Restored:** " . number_format($this->hoursRestored, 1) . " hours")
            ->line("The hours are now available in your balance for future bookings.")
            ->action('View My Quota', config('app.frontend_url') . '/dashboard/membership');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lab_booking_quota_restored',
            'title' => 'Lab Quota Restored',
            'message' => "Successfully restored " . number_format($this->hoursRestored, 1) . " hours for your booking at {$this->booking->labSpace->name}.",
            'booking_id' => $this->booking->id,
            'hours' => $this->hoursRestored,
            'link' => '/dashboard/membership',
        ];
    }

    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'user';
        return $data;
    }
}
