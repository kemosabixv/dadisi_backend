<?php

namespace App\Notifications;

use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingRescheduleNeededNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private LabBooking $booking,
        private LabMaintenanceBlock $block,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Action Required: Your Lab Booking Requires Rescheduling')
            ->greeting('Hello ' . ($notifiable->username ?? 'User') . ',')
            ->line('Your lab booking requires your attention due to a scheduled maintenance block.')
            ->line('')
            ->line('**Current Booking:**')
            ->line('Space: ' . $this->booking->labSpace->name)
            ->line('Date: ' . $this->booking->starts_at->format('l, F j, Y'))
            ->line('Time: ' . $this->booking->starts_at->format('H:i') . ' - ' . $this->booking->ends_at->format('H:i'))
            ->line('')
            ->line('**Maintenance Block:**')
            ->line('Type: ' . ucfirst($this->block->block_type))
            ->line('Title: ' . $this->block->title)
            ->line('Date: ' . $this->block->starts_at->format('l, F j, Y'))
            ->line('Time: ' . $this->block->starts_at->format('H:i') . ' - ' . $this->block->ends_at->format('H:i'))
            ->when($this->block->reason, function ($message) {
                return $message->line('Reason: ' . $this->block->reason);
            })
            ->line('')
            ->line('Unfortunately, we were unable to automatically find an alternative time slot within our system.')
            ->line('Please select a new slot for your booking.')
            ->action('Select New Slot', url('/bookings/' . $this->booking->id . '/resolve-conflict'))
            ->line('We apologize for any inconvenience.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'space_name' => $this->booking->labSpace->name,
            'booking_date' => $this->booking->starts_at->format('M j, H:i'),
            'block_type' => $this->block->block_type,
            'block_title' => $this->block->title,
            'message' => 'Your lab booking on ' . $this->booking->starts_at->format('M j, H:i') .
                ' conflicts with a scheduled ' . $this->block->block_type . '. ' .
                'Please select a new slot.',
            'requires_action' => true,
            'tier' => 'action_required',
            'action_url' => '/bookings/' . $this->booking->id . '/resolve-conflict',
        ];
    }
}
