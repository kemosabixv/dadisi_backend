<?php

namespace App\Notifications;

use App\Models\Donation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DonationPaymentFailed extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Donation $donation) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        // Only use db/supabase if the notifiable is a User (has an ID)
        if (isset($notifiable->id)) {
            $channels[] = 'database';
            $channels[] = \App\Channels\SupabaseChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $campaignTitle = $this->donation->campaign ? $this->donation->campaign->title : 'General Fund';
        $checkoutUrl = config('app.frontend_url').'/donations/checkout/'.$this->donation->reference;

        return (new MailMessage)
            ->subject('Important: Your donation payment to Dadisi Community Labs failed')
            ->greeting('Hello '.$this->donation->donor_name.',')
            ->line('We attempted to process your donation of '.$this->donation->currency.' '.number_format($this->donation->amount, 2).' to the '.$campaignTitle.', but the payment failed.')
            ->line('Common reasons for failure include insufficient funds, card expiration, or bank security blocks.')
            ->line('You can try the payment again by clicking the button below:')
            ->action('Retry Donation', $checkoutUrl)
            ->line('If you continue to experience issues, please contact your bank or support@dadisilab.com.')
            ->line('Thank you for your attempted support!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'donation_payment_failed',
            'title' => 'Payment Failed',
            'message' => "We attempted to process your donation of {$this->donation->currency} ".number_format($this->donation->amount, 2).', but the payment failed.',
            'donation_id' => $this->donation->id,
            'amount' => (float) $this->donation->amount,
            'currency' => $this->donation->currency,
            'campaign' => $this->donation->campaign?->title,
            'status' => 'failed',
            'link' => '/donations/checkout/'.$this->donation->reference,
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
