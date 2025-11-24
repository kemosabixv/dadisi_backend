<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class WelomeAndVerifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public string $code,
        public string $verifyUrl,
        public string $baseUrl
    ) {}

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Welcome to Dadisi')
                    ->markdown('emails.welcome_and_verify_email', [
                        'user' => $this->user,
                        'code' => $this->code,
                        'verifyUrl' => $this->verifyUrl,
                        'baseUrl' => $this->baseUrl,
                    ]);
    }
}
