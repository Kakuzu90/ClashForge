<?php

namespace App\Domain\Auth\Jobs;

use App\Domain\Auth\Services\UnverifiedAccountLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendVerificationLifecycleEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $userId, public readonly string $emailBinding, public readonly bool $warning, public readonly string $dispatchKey)
    {
        $this->onQueue('high');
        $this->onConnection('database');
    }

    public function handle(UnverifiedAccountLifecycleService $lifecycle): void
    {
        $start = hrtime(true);
        $context = ['user_id' => $this->userId, 'warning' => $this->warning];
        Log::info('auth.verification_lifecycle_email_started', $context);
        try {
            $lifecycle->send($this->userId, $this->emailBinding, $this->warning, $this->dispatchKey);
        } finally {
            $seconds = (hrtime(true) - $start) / 1_000_000_000;
            Log::info('auth.verification_lifecycle_email_finished', [...$context, 'duration_seconds' => $seconds]);
            if ($seconds > (int) config('platform.auth.unverified_mail_slow_seconds')) {
                Log::warning('auth.verification_lifecycle_email_slow', $context);
            }
        }
    }
}
