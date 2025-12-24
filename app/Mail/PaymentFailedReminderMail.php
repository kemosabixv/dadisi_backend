<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentFailedReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $payment;
    public $reason;

    /**
     * Create a new message instance.
     */
    public function __construct(Payment $payment, ?string $reason = null)
    {
        $this->payment = $payment;
        $this->reason = $reason;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Payment Failed — Action Required')
            ->view('emails.payment_failed_reminder')
            ->with([
                'payment' => $this->payment,
                'payable' => $this->payment->payable,
                'reason' => $this->reason,
            ]);
    }
}
