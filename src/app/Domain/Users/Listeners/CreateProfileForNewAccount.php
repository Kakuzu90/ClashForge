<?php

namespace App\Domain\Users\Listeners;

use App\Domain\Auth\Events\UserRegistered;
use App\Domain\Users\Services\ProfileService;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A new account gets its profile, privacy settings and stats rows (FR-PROFILE-1, specs/05 §3).
 * Queued, so registration does the same work whether or not the email was new. Idempotent.
 */
class CreateProfileForNewAccount implements ShouldQueue
{
    public string $queue = 'high';

    public function __construct(private readonly ProfileService $profiles) {}

    public function handle(UserRegistered $event): void
    {
        $user = User::query()->find($event->userId);

        if ($user !== null) {
            $this->profiles->createFor($user);
        }
    }
}
