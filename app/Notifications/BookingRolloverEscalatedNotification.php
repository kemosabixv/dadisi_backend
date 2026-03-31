<?php

namespace App\Notifications;

use App\Models\MaintenanceBlockRollover;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class BookingRolloverEscalatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private MaintenanceBlockRollover $rollover
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', SupabaseChannel::class, OneSignalChannel::class];
    }

    /**
     * Get the OneSignal representation of the notification.
     *
     * @param mixed $notifiable
     * @return \NotificationChannels\OneSignal\OneSignalMessage
     */
    public function toOneSignal($notifiable)
    {
        return OneSignalMessage::create()
            ->setSubject('Booking Escalation Alert')
            ->setBody("A lab booking rollover for {$this->rollover->originalBooking->labSpace->name} has been escalated.")
            ->setUrl(config('app.frontend_url') . "/admin/lab-maintenance/rollovers/" . $this->rollover->id)
            ->setData('type', 'rollover_escalation')
            ->setData('rollover_id', $this->rollover->id);
    }

    public function toSupabase(object $notifiable): array
    {
        $data = $this->toDatabase($notifiable);
        $data['recipient_type'] = 'admin'; // This is a staff notification
        return $data;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->rollover->originalBooking;
        $block = $this->rollover->maintenanceBlock;

        return (new MailMessage)
            ->subject('Staff Alert: Lab Booking Rollover Escalated')
            ->greeting('Hello ' . ($notifiable->username ?? 'Staff') . ',')
            ->line('A lab booking rollover has been escalated after 48 hours of user inactivity.')
            ->line('')
            ->line('**Original Booking:**')
            ->line('Space: ' . $booking->labSpace->name)
            ->line('User: ' . ($booking->user->username ?? $booking->guest_email))
            ->line('Date: ' . $booking->starts_at->format('l, F j, Y'))
            ->line('Time: ' . $booking->starts_at->format('H:i') . ' - ' . $booking->ends_at->format('H:i'))
            ->line('')
            ->line('**Conflicting Maintenance:**')
            ->line('Title: ' . $block->title)
            ->line('Type: ' . ucfirst($block->block_type))
            ->line('')
            ->action('View Escalation', url('/admin/lab-maintenance/rollovers/' . $this->rollover->id))
            ->line('Please resolve this conflict manually.');
    }

    public function toDatabase(object $notifiable): array
    {
        $booking = $this->rollover->originalBooking;
        return [
            'rollover_id' => $this->rollover->id,
            'booking_id' => $booking->id,
            'space_name' => $booking->labSpace->name,
            'user_name' => $booking->user->username ?? $booking->guest_email,
            'message' => 'Escalated: Booking #' . $booking->id . ' conflict unresolved after 48h.',
            'type' => 'rollover_escalation',
            'tier' => 'escalation',
        ];
    }
}
