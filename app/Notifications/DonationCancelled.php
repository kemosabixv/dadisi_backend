<?php

namespace App\Notifications;

use App\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class DonationCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(protected Donation $donation)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];
        
        if ($notifiable instanceof \App\Models\User || isset($notifiable->id)) {
            $channels[] = 'database';
            $channels[] = \App\Channels\SupabaseChannel::class;
            $channels[] = OneSignalChannel::class;
        }
        
        return $channels;
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
            ->setSubject('Donation Cancelled')
            ->setBody('Your donation attempt has been successfully cancelled.')
            ->setUrl(config('app.frontend_url') . '/donations')
            ->setData('type', 'donation_cancelled')
            ->setData('donation_id', $this->donation->id);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Donation Cancelled')
            ->greeting('Hello ' . ($this->donation->donor_name ?: 'Donor') . ',')
            ->line('Your donation attempt to Dadisi Community Labs has been successfully cancelled as per your request.')
            ->line('Amount: ' . number_format($this->donation->amount, 2) . ' ' . $this->donation->currency)
            ->line('Campaign: ' . ($this->donation->campaign?->title ?? 'General Fund'))
            ->line('If this was a mistake, or if you\'d like to start a new donation, you can always visit our donations page.')
            ->action('View Campaigns', config('app.frontend_url') . '/donations')
            ->line('Thank you for your interest in our work!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'donation_cancelled',
            'title' => 'Donation Cancelled',
            'message' => 'Your donation attempt has been successfully cancelled.',
            'donation_id' => $this->donation->id,
            'campaign_title' => $this->donation->campaign?->title,
            'amount' => (float) $this->donation->amount,
            'currency' => $this->donation->currency,
            'status' => 'cancelled',
            'link' => '/donations',
        ];
    }

    /**
     * Get the Supabase representation of the notification.
     */
    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'user';
        return $data;
    }
}
