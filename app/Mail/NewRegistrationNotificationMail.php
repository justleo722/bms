<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewRegistrationNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $usedEntryCode,
        public string $replacementEntryCode,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New BMS system registration');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-registration-notification',
            text: 'emails.new-registration-notification-text',
        );
    }
}
