<?php

namespace App\Domain\Auth\Notifications;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VerificationLifecycleEmail extends Mailable
{
    public function __construct(public readonly bool $warning, public readonly string $verificationUrl, public readonly string $deadline) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->warning ? 'Confirm your email to keep your Clash Commons account' : 'Reminder: confirm your Clash Commons email');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.auth.verification-lifecycle');
    }
}
