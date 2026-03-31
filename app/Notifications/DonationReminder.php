<?php

namespace App\Notifications;

use App\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class DonationReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Donation $donation)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if (isset($notifiable->id)) {
            $channels[] = 'database';
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
        $campaignTitle = $this->donation->campaign?->title ?? 'General Fund';

        return OneSignalMessage::create()
            ->setSubject('Donation Reminder')
            ->setBody("You have a pending donation to {$campaignTitle}. Complete it now to support our mission!")
            ->setUrl(config('app.frontend_url') . '/donations/checkout/' . $this->donation->reference)
            ->setData('type', 'donation_reminder')
            ->setData('donation_id', $this->donation->id);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $campaignTitle = $this->donation->campaign ? $this->donation->campaign->title : 'General Fund';
        $checkoutUrl = config('app.frontend_url') . '/donations/checkout/' . $this->donation->reference;

        return (new MailMessage)
            ->subject('Reminder: Complete your donation to Dadisi Community Labs')
            ->greeting('Hello ' . $this->donation->donor_name . ',')
            ->line('You recently initiated a donation of ' . $this->donation->currency . ' ' . number_format($this->donation->amount, 2) . ' to the ' . $campaignTitle . '.')
            ->line('We noticed that the payment hasn\'t been completed yet. Your support is vital to our mission and we would love to have you on board.')
            ->action('Complete Donation', $checkoutUrl)
            ->line('If you have already completed this payment, please disregard this email.')
            ->line('For assistance, please contact support@dadisilab.com.')
            ->line('Thank you for your generosity!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'donation_id' => $this->donation->id,
            'amount' => $this->donation->amount,
            'campaign' => $this->donation->campaign?->title,
            'status' => $this->donation->status,
        ];
    }
}
