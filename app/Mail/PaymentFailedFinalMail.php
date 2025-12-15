<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentFailedFinalMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subscriptionEnhancement;
    public $job;

    public function __construct($subscriptionEnhancement, $job)
    {
        $this->subscriptionEnhancement = $subscriptionEnhancement;
        $this->job = $job;
    }

    public function build()
    {
        return $this->subject('Payment failed for your subscription — action required')
            ->view('emails.payment_failed_final')
            ->with([
                'enhancement' => $this->subscriptionEnhancement,
                'job' => $this->job,
            ]);
    }
}
