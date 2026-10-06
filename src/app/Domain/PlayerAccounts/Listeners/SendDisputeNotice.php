<?php

namespace App\Domain\PlayerAccounts\Listeners;

use App\Domain\Notifications\Data\InAppMessageData;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\EmailDeliveryService;
use App\Domain\Notifications\Services\Notifier;
use App\Domain\PlayerAccounts\Enums\DisputeParty;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Events\CocAccountDisputeClosed;
use App\Domain\PlayerAccounts\Events\CocAccountDisputeEvidenceRemoved;
use App\Domain\PlayerAccounts\Events\CocAccountDisputeInfoRequested;
use App\Domain\PlayerAccounts\Events\CocAccountDisputeOpened;
use App\Domain\PlayerAccounts\Events\CocAccountDisputeReminderDue;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;

/**
 * Dispute notices to the parties (specs/16 §2, specs/13 §8, P2-18): in-app, plus the email their
 * Ownership preference allows. Each notice has an event key from the event itself, so a retried job
 * writes neither the in-app row nor the email twice.
 * A notice names the tag only, never the other party (owner decision 2026-10-06). A deleted
 * recipient is skipped by Notifier and EmailDeliveryService.
 */
class SendDisputeNotice implements ShouldQueue
{
    public string $queue = 'high';

    public function __construct(
        private readonly Notifier $notifier,
        private readonly EmailDeliveryService $emails,
    ) {}

    public function handleOpened(CocAccountDisputeOpened $event): void
    {
        $dispute = CocAccountDispute::query()->find($event->disputeId);
        if ($dispute === null || $event->holderId === null) {
            return;
        }

        $this->send($event->holderId, NotificationType::CocDisputeOpened, "{$dispute->ulid}:opened", [
            ...$this->forHolder($dispute),
            'days' => (int) config('coc.disputes.holder_response_days'),
        ]);
    }

    public function handleReminder(CocAccountDisputeReminderDue $event): void
    {
        $dispute = CocAccountDispute::query()->find($event->disputeId);
        // Late in the queue: the holder may have answered, or the dispute moved on, since.
        if ($dispute === null || $dispute->current_holder_id === null
            || ! in_array($dispute->status, [DisputeStatus::Open, DisputeStatus::AwaitingHolder], true)
            || $dispute->awaiting_since->getTimestamp() !== $event->awaitingSince) {
            return;
        }

        $this->send($dispute->current_holder_id, NotificationType::CocDisputeReminder, "{$dispute->ulid}:reminder:{$event->awaitingSince}:{$event->number}", [
            ...$this->forHolder($dispute),
            'days' => $event->daysLeft,
        ]);
    }

    public function handleInfoRequested(CocAccountDisputeInfoRequested $event): void
    {
        $dispute = CocAccountDispute::query()->find($event->disputeId);
        if ($dispute === null) {
            return;
        }

        $holder = $event->party === DisputeParty::Holder;
        $recipient = $holder ? $dispute->current_holder_id : $dispute->claimant_id;
        if ($recipient === null) {
            return;
        }

        $this->send($recipient, NotificationType::CocDisputeInfoRequested, "{$dispute->ulid}:info:{$event->awaitingSince}", [
            ...($holder ? $this->forHolder($dispute) : $this->base($dispute)),
            'days' => (int) config($holder ? 'coc.disputes.holder_response_days' : 'coc.disputes.claimant_inactive_days'),
        ]);
    }

    /**
     * Who hears about each ending (owner decision 2026-10-06): both parties of an admin decision;
     * the other party when one side released or withdrew; nobody when the claimant's own token
     * ended it, since the verified and takeover notices already say so.
     */
    public function handleClosed(CocAccountDisputeClosed $event): void
    {
        $dispute = CocAccountDispute::query()->find($event->disputeId);
        if ($dispute === null) {
            return;
        }

        [$claimant, $holder] = match (true) {
            $event->status === DisputeStatus::ResolvedTransfer && $event->closedBy === 'admin' => ['transferred_to_you', 'transferred_away'],
            $event->status === DisputeStatus::ResolvedTransfer => ['released_to_you', null],
            $event->status === DisputeStatus::ResolvedDenied && $event->closedBy === 'admin' => ['denied', 'kept'],
            $event->status === DisputeStatus::ResolvedDenied => ['denied_token', null],
            $event->status === DisputeStatus::ResolvedSuspended => ['suspended', 'suspended'],
            $event->status === DisputeStatus::Withdrawn && $event->closedBy === 'sweep' => ['withdrawn_inactive', 'withdrawn'],
            $event->status === DisputeStatus::Withdrawn => [null, 'withdrawn'],
            $event->status === DisputeStatus::AutoResolved && $event->by === 'other_token' => ['verified_by_other', 'verified_by_other'],
            default => [null, null],
        };

        if ($claimant !== null) {
            $own = CocAccount::query()->where('user_id', $dispute->claimant_id)->where('tag_normalized', $dispute->tag_normalized)->value('ulid');
            $this->send($dispute->claimant_id, NotificationType::CocDisputeClosed, "{$dispute->ulid}:closed", [
                ...$this->base($dispute),
                'outcome' => $claimant,
                'account' => in_array($claimant, ['transferred_to_you', 'released_to_you'], true) && is_string($own) ? $own : null,
            ]);
        }
        if ($holder !== null && $dispute->current_holder_id !== null) {
            $this->send($dispute->current_holder_id, NotificationType::CocDisputeClosed, "{$dispute->ulid}:closed", [
                ...$this->forHolder($dispute),
                'outcome' => $holder,
            ]);
        }
    }

    /**
     * In-app only (P2-25 Q4): it tells the uploader what to send instead, nothing to act on by email.
     */
    public function handleEvidenceRemoved(CocAccountDisputeEvidenceRemoved $event): void
    {
        $dispute = CocAccountDispute::query()->find($event->disputeId);
        $user = User::query()->find($event->userId);
        if ($dispute === null || $user === null) {
            return;
        }

        $this->notifier->sendOnce($user, new InAppMessageData(NotificationType::CocDisputeEvidenceRemoved, $this->base($dispute)), "{$dispute->ulid}:evidence_removed:{$event->mediaUlid}");
    }

    /**
     * @param  array<string, string|int|bool|null>  $params
     */
    private function send(int $userId, NotificationType $type, string $eventKey, array $params): void
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return;
        }

        $this->notifier->sendOnce($user, new InAppMessageData($type, $params), $eventKey);
        $this->emails->queue($userId, $type, $eventKey, $params);
    }

    /**
     * @return array{tag: string, dispute: string}
     */
    private function base(CocAccountDispute $dispute): array
    {
        return ['tag' => '#'.$dispute->tag_normalized, 'dispute' => $dispute->ulid];
    }

    /**
     * The holder's notices link to their account page, which shows the review (specs/18 §6).
     *
     * @return array{tag: string, dispute: string, account: string|null}
     */
    private function forHolder(CocAccountDispute $dispute): array
    {
        return [...$this->base($dispute), 'account' => CocAccount::query()->whereKey($dispute->coc_account_id)->value('ulid')];
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            CocAccountDisputeOpened::class => 'handleOpened',
            CocAccountDisputeReminderDue::class => 'handleReminder',
            CocAccountDisputeInfoRequested::class => 'handleInfoRequested',
            CocAccountDisputeClosed::class => 'handleClosed',
            CocAccountDisputeEvidenceRemoved::class => 'handleEvidenceRemoved',
        ];
    }
}
