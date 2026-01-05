<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundProcessed extends Notification
{

    public function __construct(
        protected Refund $refund
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->refund->status;
        $isCompleted = $status === Refund::STATUS_COMPLETED;
        
        $mail = (new MailMessage)
            ->subject($isCompleted ? 'Refund Processed' : 'Refund Update')
            ->greeting("Hello!");

        if ($isCompleted) {
            $mail->line('Your refund has been successfully processed.')
                ->line("**Amount:** {$this->refund->currency} " . number_format((float) $this->refund->amount, 2))
                ->line('The refund will be credited to your original payment method within 5-10 business days.');
        } elseif ($status === Refund::STATUS_REJECTED) {
            $mail->line('Unfortunately, your refund request has been declined.')
                ->when($this->refund->admin_notes, fn($m) => $m->line("**Reason:** {$this->refund->admin_notes}"));
        } else {
            $mail->line("Your refund request status has been updated to: {$status}");
        }

        return $mail
            ->line("**Reference:** #{$this->refund->id}")
            ->line('If you have any questions, please contact our support team.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'refund_processed',
            'title' => $this->refund->status === Refund::STATUS_COMPLETED 
                ? 'Refund Completed' 
                : 'Refund Update',
            'message' => $this->getStatusMessage(),
            'refund_id' => $this->refund->id,
            'amount' => $this->refund->amount,
            'currency' => $this->refund->currency,
            'status' => $this->refund->status,
            'link' => '/dashboard',
        ];
    }

    protected function getStatusMessage(): string
    {
        return match ($this->refund->status) {
            Refund::STATUS_COMPLETED => "Your refund of {$this->refund->currency} " . 
                number_format((float) $this->refund->amount, 2) . " has been processed.",
            Refund::STATUS_REJECTED => "Your refund request has been declined.",
            Refund::STATUS_APPROVED => "Your refund request has been approved and is being processed.",
            default => "Your refund status has been updated to {$this->refund->status}.",
        };
    }
}
