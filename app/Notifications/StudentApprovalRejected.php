<?php

namespace App\Notifications;

use App\Models\StudentApprovalRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;

class StudentApprovalRejected extends Notification
{
    public function __construct(
        protected StudentApprovalRequest $approvalRequest,
        protected string|null $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', SupabaseChannel::class, \NotificationChannels\OneSignal\OneSignalChannel::class];
    }

    /**
     * Get the OneSignal representation of the notification.
     *
     * @param mixed $notifiable
     * @return \NotificationChannels\OneSignal\OneSignalMessage
     */
    public function toOneSignal($notifiable)
    {
        return \NotificationChannels\OneSignal\OneSignalMessage::create()
            ->setSubject('Student Status Rejected')
            ->setBody("Your student status request for {$this->approvalRequest->student_institution} has been rejected.")
            ->setUrl(config('app.frontend_url') . '/dashboard/membership')
            ->setData('type', 'student_approval_rejected')
            ->setData('request_id', $this->approvalRequest->id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->error()
            ->subject('Student Approval Request Rejected')
            ->greeting("Hello, {$notifiable->username}!")
            ->line("Regrettably, your student approval request for {$this->approvalRequest->student_institution} has been rejected.");

        if ($this->reason) {
            $mail->line("**Reason:** {$this->reason}");
        }

        return $mail
            ->line('If you believe this is an error, please update your documentation and try again.')
            ->action('View My Requests', url('/dashboard/membership'))
            ->line('Thank you for your interest in Dadisi Community Labs.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'student_approval_rejected',
            'title' => 'Student Approval Rejected',
            'message' => "Your student approval request for {$this->approvalRequest->student_institution} has been rejected.",
            'request_id' => $this->approvalRequest->id,
            'institution' => $this->approvalRequest->student_institution,
            'reason' => $this->reason,
            'link' => '/dashboard/membership',
        ];
    }

    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'user';
        return $data;
    }
}
