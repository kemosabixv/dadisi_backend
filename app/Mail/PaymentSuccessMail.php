<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $payment;

    /**
     * Create a new message instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $payableType = $this->payment->payable_type;
        $subject = 'Payment Received — Thank You!';
        
        if (str_contains($payableType, 'EventOrder')) {
            $subject = 'Event Registration Confirmed — ' . $this->payment->order_reference;
        } elseif (str_contains($payableType, 'Donation')) {
            $subject = 'Thank you for your donation — ' . $this->payment->order_reference;
        } elseif (str_contains($payableType, 'Subscription')) {
            $subject = 'Subscription Activated — Welcome to ' . config('app.name');
        }

        return $this->subject($subject)
            ->view('emails.payment_success')
            ->with([
                'payment' => $this->payment,
                'payable' => $this->payment->payable,
            ]);
    }
}
