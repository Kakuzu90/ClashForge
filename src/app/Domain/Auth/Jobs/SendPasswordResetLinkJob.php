<?php

namespace App\Domain\Auth\Jobs;

use App\Domain\Auth\Services\PasswordResetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Looks the email up and sends the link off the request path. The request only validates and
 * queues this job, so it takes the same time whether or not the account exists (specs/11).
 */
class SendPasswordResetLinkJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $email)
    {
        $this->onQueue('high');
    }

    public function handle(PasswordResetService $passwords): void
    {
        // Unknown email or the broker's per-account minute: nothing to send, nothing to report.
        $passwords->sendLink($this->email);
    }
}
