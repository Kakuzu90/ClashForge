<?php

namespace App\Domain\Moderation\Listeners;

use App\Domain\Moderation\Enums\SanctionType;
use App\Domain\Moderation\Events\SanctionApplied;
use App\Domain\Moderation\Events\SanctionLifted;
use App\Domain\Moderation\Models\UserSanction;
use App\Domain\Moderation\Notifications\AccountBannedNotification;
use App\Domain\Moderation\Notifications\AccountSuspendedNotification;
use App\Domain\Moderation\Notifications\SanctionEndedNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;

/**
 * Tells the account holder about a sanction and its end (FR-MOD-8, specs/16 §2). Queued, as
 * listeners that do I/O are (specs/05 §2).
 */
class SendSanctionNotice implements ShouldQueue
{
    public string $queue = 'high';

    public function handleApplied(SanctionApplied $event): void
    {
        $sanction = UserSanction::query()->find($event->sanctionId);
        $user = User::query()->withTrashed()->find($event->userId);

        // Lifted or replaced before this ran: telling them about it now would be wrong.
        if ($sanction === null || $user === null || ! $sanction->isActive()) {
            return;
        }

        $user->notify(match ($sanction->type) {
            SanctionType::Ban => new AccountBannedNotification($sanction->public_reason),
            default => new AccountSuspendedNotification($sanction->public_reason, $sanction->expires_at ?? $sanction->starts_at),
        });
    }

    public function handleLifted(SanctionLifted $event): void
    {
        $sanction = UserSanction::query()->find($event->sanctionId);
        $user = User::query()->withTrashed()->find($event->userId);

        // A newer sanction may have started since; the account is not "back to normal" then.
        if ($sanction === null || $user === null || $sanction->isActive() || $this->hasActiveSanction($user)) {
            return;
        }

        $user->notify(new SanctionEndedNotification($sanction->type, $event->expired));
    }

    private function hasActiveSanction(User $user): bool
    {
        return UserSanction::query()->active()->where('user_id', $user->id)->exists();
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            SanctionApplied::class => 'handleApplied',
            SanctionLifted::class => 'handleLifted',
        ];
    }
}
