<?php

namespace App\Notifications;

use App\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DonationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Donation $donation
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $campaign = $this->donation->campaign;
        
        return (new MailMessage)
            ->subject('Thank You for Your Donation!')
            ->greeting("Dear {$this->donation->donor_name},")
            ->line('Thank you for your generous donation to Dadisi Community Labs.')
            ->when($campaign, fn($mail) => $mail->line("**Campaign:** {$campaign->title}"))
            ->line("**Amount:** {$this->donation->currency} " . number_format((float) $this->donation->amount, 2))
            ->line("**Reference:** {$this->donation->reference}")
            ->line('Your contribution helps us continue our mission to empower communities through science and innovation.')
            ->action('View Your Donations', url('/dashboard/donations'))
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
            'amount' => $this->donation->amount,
            'currency' => $this->donation->currency,
            'link' => '/dashboard/donations',
        ];
    }
}
