<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\Audit\Data\AuditActorData;
use App\Domain\Audit\Data\AuditEntryData;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Events\CocAccountDisputeClosed;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Models\User;
use Illuminate\Support\Facades\Date;

/**
 * Status changes of a dispute with their audit entry and event, shared by DisputeService and token
 * verification (specs/13 §3.1 step 8, §5). Always inside the caller's transaction.
 */
final class DisputeLedger
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Ends a dispute. A holder row still `disputed` goes back to `verified` unless the caller
     * already moved it (a transfer, a suspension, a token from someone else). `$closedBy` is one of
     * claimant, holder, admin, token, sweep.
     *
     * @param  array<string, mixed>  $context
     */
    public function close(CocAccountDispute $dispute, DisputeStatus $status, string $closedBy, ?User $actor, ?User $decidedBy = null, array $context = []): void
    {
        $before = $dispute->status;
        $dispute->forceFill([
            'status' => $status,
            'decided_by' => $decidedBy?->id,
            'decided_at' => Date::now(),
            'closed_by' => $closedBy,
        ])->save();

        CocAccount::query()->whereKey($dispute->coc_account_id)->where('status', CocAccountStatus::Disputed)
            ->update(['status' => CocAccountStatus::Verified]);

        $this->record($actor, AuditAction::CocDisputeClosed, $dispute, ['status' => $before->value], ['status' => $status->value], $context);
        CocAccountDisputeClosed::dispatch($dispute->id, $status, $closedBy, is_string($context['by'] ?? null) ? $context['by'] : null);
    }

    /**
     * A token decided it (specs/13 §3.1 step 8, §5 3a): the holder's own token denies the claim,
     * anyone else's resolves it. Called after the rows of the tag were updated.
     */
    public function closeOnVerification(PlayerTag $tag, User $verifier): void
    {
        $disputes = CocAccountDispute::query()->active()->where('tag_normalized', $tag->bare())->lockForUpdate()->get();

        foreach ($disputes as $dispute) {
            $byHolder = $dispute->current_holder_id === $verifier->id;
            $this->close($dispute, $byHolder ? DisputeStatus::ResolvedDenied : DisputeStatus::AutoResolved, 'token', $verifier, context: [
                'by' => $byHolder ? 'holder_token' : ($dispute->claimant_id === $verifier->id ? 'claimant_token' : 'other_token'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $context
     */
    public function record(?User $actor, AuditAction $action, CocAccountDispute $dispute, ?array $before, ?array $after, array $context = []): void
    {
        $this->audit->record(new AuditEntryData(
            actor: $actor === null ? AuditActorData::scheduler() : new AuditActorData(id: $actor->id, role: $actor->role->value),
            action: $action,
            subject: AuditSubject::CocAccountDispute,
            subjectId: $dispute->id,
            before: $before,
            after: $after,
            context: ['dispute' => $dispute->ulid, 'tag' => '#'.$dispute->tag_normalized, ...$context],
        ));
    }
}
