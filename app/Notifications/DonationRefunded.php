<?php

namespace App\Notifications;

use App\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DonationRefunded extends Notification implements ShouldQueue
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
        
        if ($this->donation->user_id) {
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
        return (new MailMessage)
            ->subject('Donation Refund Processed')
            ->greeting('Hello ' . ($this->donation->donor_name ?: 'Donor') . ',')
            ->line('This is to inform you that a refund has been processed for your donation to Dadisi Community Labs.')
            ->line('Amount Refunded: ' . number_format($this->donation->amount, 2) . ' ' . $this->donation->currency)
            ->line('Campaign: ' . ($this->donation->campaign?->title ?? 'General Fund'))
            ->line('The amount should appear in your account within the next few business days, depending on your bank or payment provider.')
            ->line('If you have any questions regarding this refund, please contact our support team.')
            ->line('Thank you for your initial interest in supporting our mission!');
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
            'campaign_title' => $this->donation->campaign?->title,
            'amount' => (float) $this->donation->amount,
            'currency' => $this->donation->currency,
            'status' => 'refunded',
            'message' => 'Refund for donation of ' . $this->donation->amount . ' ' . $this->donation->currency . ' has been processed.',
        ];
    }

    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'user';
        return $data;
    }
}
