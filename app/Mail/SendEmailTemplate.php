<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendEmailTemplate extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $html;

    public function __construct($html)
    {
        $this->html = $html;
    }

    public function build()
    {
        return $this->subject("Add Recruiter")
            ->view('emails.email-template',  ['html' =>  $this->html]);
    }
}
