<?php

namespace App\Notifications;

use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingRescheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private LabBooking $booking,
        private Carbon $oldStartsAt,
        private Carbon $oldEndsAt,
        private string $reason = '',
        private ?LabMaintenanceBlock $block = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Lab Booking Has Been Rescheduled')
            ->greeting('Hello ' . ($notifiable->username ?? 'User') . ',')
            ->line('We are writing to inform you that your lab booking has been automatically rescheduled.')
            ->line('**Original Booking:**')
            ->line('Space: ' . $this->booking->labSpace->name)
            ->line('Date: ' . $this->oldStartsAt->format('l, F j, Y'))
            ->line('Time: ' . $this->oldStartsAt->format('H:i') . ' - ' . $this->oldEndsAt->format('H:i'))
            ->line('')
            ->line('**New Booking:**')
            ->line('Space: ' . $this->booking->labSpace->name)
            ->line('Date: ' . $this->booking->starts_at->format('l, F j, Y'))
            ->line('Time: ' . $this->booking->starts_at->format('H:i') . ' - ' . $this->booking->ends_at->format('H:i'))
            ->line('')
            ->when($this->reason || $this->block, function ($message) {
                $message->line('**Reason for Change:**');
                if ($this->block) {
                    return $message->line(
                        ucfirst($this->block->block_type) . ': ' .
                        $this->block->title .
                        ($this->block->reason ? ' (' . $this->block->reason . ')' : '')
                    );
                }
                return $message->line($this->reason);
            })
            ->line('If this new time does not work for you, please contact us as soon as possible.')
            ->action('View Booking', route('lab-bookings.show', $this->booking->id, absolute: true))
            ->line('Thank you for your understanding.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'space_name' => $this->booking->labSpace->name,
            'old_starts_at' => $this->oldStartsAt,
            'old_ends_at' => $this->oldEndsAt,
            'new_starts_at' => $this->booking->starts_at,
            'new_ends_at' => $this->booking->ends_at,
            'reason' => $this->reason ?: ($this->block ? $this->block->title : ''),
            'block_type' => $this->block?->block_type,
            'block_title' => $this->block?->title,
            'message' => 'Your lab booking has been rescheduled from ' .
                $this->oldStartsAt->format('M j, H:i') . ' to ' .
                $this->booking->starts_at->format('M j, H:i'),
            'tier' => 'informational',
        ];
    }
}
