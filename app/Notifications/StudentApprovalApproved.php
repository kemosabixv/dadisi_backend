<?php

namespace App\Notifications;

use App\Models\StudentApprovalRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;

class StudentApprovalApproved extends Notification
{
    public function __construct(
        protected StudentApprovalRequest $approvalRequest
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', SupabaseChannel::class, \NotificationChannels\WebPush\WebPushChannel::class];
    }

    /**
     * Get the WebPush representation of the notification.
     */
    public function toWebPush($notifiable, $notification)
    {
        return (new \NotificationChannels\WebPush\WebPushMessage)
            ->title('Student Status Approved')
            ->icon('/logo.png')
            ->body('Congratulations! Your student status has been approved. You now have access to student-exclusive perks.')
            ->action('View Dashboard', 'view_dashboard');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Student Approval Request Approved')
            ->greeting("Hello, {$notifiable->username}!")
            ->line("Your student approval request for {$this->approvalRequest->student_institution} has been approved.")
            ->line('You can now proceed to subscribe to student plans.')
            ->action('View My Dashboard', url('/dashboard'))
            ->line('Thank you for being part of Dadisi Community Labs!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'student_approval_approved',
            'title' => 'Student Approval Approved',
            'message' => "Your student approval request for {$this->approvalRequest->student_institution} has been approved.",
            'request_id' => $this->approvalRequest->id,
            'institution' => $this->approvalRequest->student_institution,
            'link' => '/dashboard',
        ];
    }

    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'user';
        return $data;
    }
}
