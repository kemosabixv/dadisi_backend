<?php

namespace App\Notifications;

use App\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class DonationInitiated extends Notification
{
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
        // For guest donors, we only send mail. 
        // For users, we add 'database' and 'supabase' for dashboard updates.
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
        $campaignTitle = $this->donation->campaign?->title ?? 'General Fund';
        
        return OneSignalMessage::create()
            ->setSubject('Donation Pending')
            ->setBody("You started a donation to {$campaignTitle}. Would you like to complete it?")
            ->setUrl(config('app.frontend_url') . '/donations/checkout/' . $this->donation->reference)
            ->setData('type', 'donation_initiated')
            ->setData('donation_id', $this->donation->id);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $checkoutUrl = config('app.frontend_url') . '/donations/checkout/' . $this->donation->reference;

        return (new MailMessage)
            ->subject('Thank You for Starting Your Donation')
            ->greeting('Hello ' . ($this->donation->donor_name ?: 'Donor') . ',')
            ->line('We noticed you started a donation to Dadisi Community Labs but haven\'t completed the payment yet.')
            ->line('Amount: ' . number_format($this->donation->amount, 2) . ' ' . $this->donation->currency)
            ->line('Campaign: ' . ($this->donation->campaign?->title ?? 'General Fund'))
            ->line('If you were interrupted, you can resume your donation by clicking the button below.')
            ->action('Complete Donation', $checkoutUrl)
            ->line('If you have already completed this payment, please disregard this email.')
            ->line('For assistance, please contact support@dadisilab.com.')
            ->line('Thank you for supporting our mission!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'donation_initiated',
            'title' => 'Donation Started',
            'message' => 'Thank you for starting a donation to ' . ($this->donation->campaign?->title ?? 'General Fund') . '.',
            'donation_id' => $this->donation->id,
            'campaign_title' => $this->donation->campaign?->title,
            'amount' => (float) $this->donation->amount,
            'currency' => $this->donation->currency,
            'status' => 'initiated',
            'link' => '/donations/checkout/' . $this->donation->reference,
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
