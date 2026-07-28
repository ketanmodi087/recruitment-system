<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class SetPasswordForNewSignup extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */

    public $user;

    public function __construct($user) {
        $this->user = $user;
    }

    public function build()
    {
        return $this->subject("New User Set Password")
            ->view('emails.set-password-newsignup-email');
    }
}
