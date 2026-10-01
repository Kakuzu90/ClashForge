<?php

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\Data\RenderedNotificationData;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class NonSecurityEmail extends Mailable
{
    public function __construct(public readonly RenderedNotificationData $notice, public readonly string $unsubscribeUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notice->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.notifications.non-security');
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>']);
    }
}
