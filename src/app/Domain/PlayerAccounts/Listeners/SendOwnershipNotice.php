<?php

namespace App\Domain\PlayerAccounts\Listeners;

use App\Domain\Notifications\Data\InAppMessageData;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\EmailDeliveryService;
use App\Domain\Notifications\Services\Notifier;
use App\Domain\PlayerAccounts\Enums\VerificationMethod;
use App\Domain\PlayerAccounts\Events\CocAccountOwnershipTransferred;
use App\Domain\PlayerAccounts\Events\CocAccountReleased;
use App\Domain\PlayerAccounts\Events\CocAccountVerified;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Notifications\CocAccountTakenOverNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;

/**
 * Ownership notices (specs/13 §8, specs/16 §2). The verifier gets "verified" in-app, plus an
 * email that follows their preferences and the daily cap. The previous holder of a superseded tag
 * gets the takeover notice, whose email is always sent. When two tokens arrive seconds apart, both
 * users hear about it (specs/13 §9), so a notice reports the event even if the row changed since.
 * A released tag gets an in-app notice to the user who held it (specs/16 §2 "Tag released").
 */
class SendOwnershipNotice implements ShouldQueue
{
    public string $queue = 'high';

    public function __construct(
        private readonly Notifier $notifier,
        private readonly EmailDeliveryService $emails,
    ) {}

    public function handleVerified(CocAccountVerified $event): void
    {
        $account = CocAccount::query()->find($event->accountId);
        $user = User::query()->find($event->userId);

        // A deleted account reads nothing; there is no one to tell.
        if ($account === null || $user === null) {
            return;
        }

        $params = $this->params($account);
        $this->notifier->send($user, new InAppMessageData(NotificationType::CocAccountVerified, $params));
        $this->emails->queue($user->id, NotificationType::CocAccountVerified, (string) $event->claimId, $params);
    }

    public function handleTransferred(CocAccountOwnershipTransferred $event): void
    {
        // A dispute decision is announced to both parties by the dispute's own notice (P2-18).
        if ($event->method === VerificationMethod::Admin) {
            return;
        }

        $account = CocAccount::query()->find($event->accountId);
        $previous = User::query()->find($event->fromUserId);

        if ($account === null || $previous === null) {
            return;
        }

        // The tag only: whoever took the account over chooses its in-game name, and this email is
        // always sent, so the name could carry their text into our security notice.
        $previous->notify(new CocAccountTakenOverNotification(['tag' => $account->tag, 'method' => $event->method->value]));
    }

    public function handleReleased(CocAccountReleased $event): void
    {
        $account = CocAccount::query()->find($event->accountId);
        $owner = User::query()->find($event->userId);

        if ($account === null || $owner === null) {
            return;
        }

        // No account link: the row is no longer theirs.
        $this->notifier->send($owner, new InAppMessageData(NotificationType::CocAccountReleased, ['tag' => $account->tag, 'name' => $account->ign]));
    }

    /**
     * The tag, in-game name and the account page's ulid only: never who verified it (specs/16 §4).
     *
     * @return array{tag: string, name: string, account: string}
     */
    private function params(CocAccount $account): array
    {
        return ['tag' => $account->tag, 'name' => $account->ign, 'account' => $account->ulid];
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            CocAccountVerified::class => 'handleVerified',
            CocAccountOwnershipTransferred::class => 'handleTransferred',
            CocAccountReleased::class => 'handleReleased',
        ];
    }
}
