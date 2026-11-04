<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $signInCode,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your BMS account and sign-in code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-welcome',
            text: 'emails.registration-welcome-text',
        );
    }
}
