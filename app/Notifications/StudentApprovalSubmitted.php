<?php

namespace App\Notifications;

use App\Models\StudentApprovalRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to admins when a student approval request is submitted.
 * 
 * This notification is synchronous (not queued) because the target 
 * shared hosting environment (PHP-FPM) cannot run persistent queue workers.
 */
class StudentApprovalSubmitted extends Notification
{
    public function __construct(
        protected StudentApprovalRequest $approvalRequest
    ) {}

    public function via(object $notifiable): array
    {
        // Database first to ensure notification is stored even if mail fails
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $userName = $this->approvalRequest->user->username ?? 'A member';
        $institution = $this->approvalRequest->student_institution;

        return (new MailMessage)
            ->subject('New Student Approval Request Submitted')
            ->greeting("Hello, {$notifiable->username}!")
            ->line("A new student approval request has been submitted by {$userName}.")
            ->line("Institution: {$institution}")
            ->line("County: {$this->approvalRequest->county}")
            ->action('Review Request', url('/admin/membership/approvals'))
            ->line('Please log in to the admin panel to review the documentation and approve or reject the request.');
    }

    public function toArray(object $notifiable): array
    {
        $userName = $this->approvalRequest->user->username ?? 'A member';
        
        return [
            'type' => 'student_approval_submitted',
            'title' => 'New Student Approval Request',
            'message' => "{$userName} has submitted a student approval request for {$this->approvalRequest->student_institution}.",
            'request_id' => $this->approvalRequest->id,
            'user_id' => $this->approvalRequest->user_id,
            'user_name' => $userName,
            'institution' => $this->approvalRequest->student_institution,
            'link' => '/admin/membership/approvals',
        ];
    }
}
