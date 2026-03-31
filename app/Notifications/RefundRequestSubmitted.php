<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

/**
 * Notification sent to admins when a refund request is submitted.
 */
class RefundRequestSubmitted extends Notification
{
    public function __construct(
        protected Refund $refund
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', SupabaseChannel::class, OneSignalChannel::class];
    }

    /**
     * Get the OneSignal representation of the notification.
     *
     * @param mixed $notifiable
     * @return \NotificationChannels\OneSignal\OneSignalMessage
     */
    public function toOneSignal($notifiable)
    {
        $requester = $this->getRequesterName();
        $amount = number_format($this->refund->amount, 2);

        return OneSignalMessage::create()
            ->setSubject('New Refund Request')
            ->setBody("{$requester} submitted a refund request for {$this->refund->currency} {$amount}.")
            ->setUrl(config('app.frontend_url') . '/admin/finance/refunds')
            ->setData('type', 'refund_request_submitted')
            ->setData('refund_id', $this->refund->id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $requester = $this->getRequesterName();
        $refundable = $this->refund->refundable;
        $type = str_replace(['App\\Models\\', 'EventOrder'], ['', 'Event Ticket'], get_class($refundable));

        return (new MailMessage)
            ->subject('New Refund Request Submitted')
            ->greeting("Hello, {$notifiable->username}!")
            ->line("A new refund request has been submitted by {$requester}.")
            ->line("Type: {$type}")
            ->line("Amount: {$this->refund->currency} " . number_format($this->refund->amount, 2))
            ->line("Reason: " . ($this->refund->reason_display ?? $this->refund->reason))
            ->when($this->refund->customer_notes, fn($m) => $m->line("Customer Notes: {$this->refund->customer_notes}"))
            ->action('Review Refund', url('/admin/finance/refunds'))
            ->line('Please log in to the admin panel to review the request.');
    }

    public function toArray(object $notifiable): array
    {
        $requester = $this->getRequesterName();
        $requesterDetails = $this->getRequesterDetails();

        return [
            'type' => 'refund_request_submitted',
            'title' => 'New Refund Request',
            'message' => "{$requester} has submitted a refund request for {$this->refund->currency} " . number_format($this->refund->amount, 2) . ".",
            'refund_id' => $this->refund->id,
            'amount' => (float) $this->refund->amount,
            'currency' => $this->refund->currency,
            'reason' => $this->refund->reason_display ?? $this->refund->reason,
            'requester_name' => $requester,
            'requester_email' => $requesterDetails['email'],
            'requester_user_id' => $requesterDetails['user_id'],
            'is_guest' => $requesterDetails['is_guest'],
            'item_title' => $requesterDetails['item_title'],
            'link' => '/admin/finance/refunds',
        ];
    }

    /**
     * Get the Supabase representation of the notification.
     */
    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'staff';
        $data['permission'] = 'can_manage_refunds';

        return $data;
    }

    protected function getRequesterName(): string
    {
        $refundable = $this->refund->refundable;
        if (!$refundable) return 'A customer';

        if (method_exists($refundable, 'user') && $refundable->user) {
            return $refundable->user->username ?? $refundable->user->name ?? 'A member';
        }

        if (isset($refundable->guest_name) && $refundable->guest_name) {
            return $refundable->guest_name . ' (Guest)';
        }

        if (isset($refundable->guest_email) && $refundable->guest_email) {
            return $refundable->guest_email . ' (Guest)';
        }

        return 'A guest';
    }

    protected function getRequesterDetails(): array
    {
        $refundable = $this->refund->refundable;

        $details = [
            'email' => null,
            'user_id' => null,
            'is_guest' => true,
            'item_title' => $this->refund->refundable_title,
        ];

        if (!$refundable) return $details;

        if (method_exists($refundable, 'user') && $refundable->user) {
            $details['email'] = $refundable->user->email;
            $details['user_id'] = $refundable->user->id;
            $details['is_guest'] = false;
        } elseif (isset($refundable->guest_email)) {
            $details['email'] = $refundable->guest_email;
        }

        return $details;
    }
}
