<?php

namespace App\Notifications;

use App\Models\Donation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DonationReceived extends Notification
{

    public function __construct(
        protected Donation $donation
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof \App\Models\User 
            ? ['mail', 'database', \App\Channels\SupabaseChannel::class, \NotificationChannels\OneSignal\OneSignalChannel::class] 
            : ['mail'];
    }

    /**
     * Get the OneSignal representation of the notification.
     *
     * @param mixed $notifiable
     * @return \NotificationChannels\OneSignal\OneSignalMessage
     */
    public function toOneSignal($notifiable)
    {
        $amount = number_format((float) $this->donation->amount, 2);
        
        return \NotificationChannels\OneSignal\OneSignalMessage::create()
            ->setSubject('Donation Received')
            ->setBody("Thank you for your donation of {$this->donation->currency} {$amount}!")
            ->setUrl(config('app.frontend_url') . '/dashboard/donations')
            ->setData('type', 'donation_received')
            ->setData('donation_id', $this->donation->id);
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

    public function toMail(object $notifiable): MailMessage
    {
        $campaign = $this->donation->campaign;
        $actionUrl = $this->donation->user_id 
            ? config('app.frontend_url') . '/dashboard/donations' 
            : config('app.frontend_url') . '/donations/receipt/' . $this->donation->reference;
        
        return (new MailMessage)
            ->subject('Thank You for Your Donation!')
            ->greeting("Dear {$this->donation->donor_name},")
            ->line('Thank you for your generous donation to Dadisi Community Labs.')
            ->when($campaign, fn($mail) => $mail->line("**Campaign:** {$campaign->title}"))
            ->line("**Amount:** {$this->donation->currency} " . number_format((float) $this->donation->amount, 2))
            ->line("**Reference:** {$this->donation->reference}")
            ->line('Your contribution helps us continue our mission to empower communities through science and innovation.')
            ->action($this->donation->user_id ? 'View Your Donations' : 'View Donation Receipt', $actionUrl)
            ->line('For any queries regarding this donation, please contact support@dadisilab.com.')
            ->line('Thank you for your support!');
    }

    public function toArray(object $notifiable): array
    {
        $campaign = $this->donation->campaign;
        
        return [
            'type' => 'donation_received',
            'title' => 'Donation Received',
            'message' => "Thank you for your donation of {$this->donation->currency} " . 
                number_format((float) $this->donation->amount, 2),
            'donation_id' => $this->donation->id,
            'campaign_id' => $campaign?->id,
            'campaign_title' => $campaign?->title,
            'amount' => (float) $this->donation->amount,
            'currency' => $this->donation->currency,
            'link' => '/dashboard/donations',
        ];
    }
}
