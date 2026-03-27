<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;

class RefundRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Refund $refund,
        protected string $reason
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }
        return ['mail', 'database', SupabaseChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->refund->refundable_title;

        return (new MailMessage)
            ->subject('Refund Request Rejected: ' . $title)
            ->greeting('Hello!')
            ->line('Your refund request has been reviewed and unfortunately rejected.')
            ->line('Reason for Rejection: ' . $this->reason)
            ->line('Amount: ' . $this->refund->currency . ' ' . number_format($this->refund->amount, 2))
            ->action('Contact Support', url('/support'))
            ->line('If you believe this is an error, please contact our support team.');
    }

    public function toArray(object $notifiable): array
    {
        $title = $this->refund->refundable_title;

        return [
            'type' => 'refund_rejected',
            'title' => 'Refund Request Rejected',
            'message' => "Your refund request for {$title} was rejected: {$this->reason}",
            'refund_id' => $this->refund->id,
            'reason' => $this->reason,
            'link' => '/support',
        ];
    }

    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'user';
        return $data;
    }
}
