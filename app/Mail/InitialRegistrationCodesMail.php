<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InitialRegistrationCodesMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $codes
     */
    public function __construct(public array $codes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your BMS registration entry codes');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.initial-registration-codes',
            text: 'emails.initial-registration-codes-text',
        );
    }
}
