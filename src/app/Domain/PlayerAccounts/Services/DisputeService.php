<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Services\UserStatusService;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Services\MediaAttachmentService;
use App\Domain\Moderation\Enums\ModerationActionType;
use App\Domain\Moderation\Enums\ReasonCode;
use App\Domain\Moderation\Services\ModerationActionLog;
use App\Domain\PlayerAccounts\Data\DisputeResultData;
use App\Domain\PlayerAccounts\Enums\ClaimMethod;
use App\Domain\PlayerAccounts\Enums\ClaimStatus;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use App\Domain\PlayerAccounts\Enums\DisputeParty;
use App\Domain\PlayerAccounts\Enums\DisputeRefusal;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Enums\VerificationMethod;
use App\Domain\PlayerAccounts\Events\CocAccountDisputeInfoRequested;
use App\Domain\PlayerAccounts\Events\CocAccountDisputeOpened;
use App\Domain\PlayerAccounts\Events\CocAccountOwnershipTransferred;
use App\Domain\PlayerAccounts\Events\CocAccountVerified;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Support\AccountRows;
use App\Domain\PlayerAccounts\Support\ClaimRecorder;
use App\Domain\PlayerAccounts\Support\DisputeLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Ownership disputes (specs/13 §5): for the claimant who owns the game account but cannot produce
 * a token. A dispute holds the holder's row in `disputed` (their rights stay) until a token, the
 * holder, the claimant or an admin ends it. Every change runs in one transaction that locks the
 * tag's rows in id order, then the users involved, as verification does (specs/13 §3.1).
 *
 * Decision bias: without decisive evidence the holder keeps the tag (specs/13 §5); a deny is the
 * default outcome, and non-response alone never transfers.
 */
class DisputeService
{
    public function __construct(
        private readonly AccountRows $rows,
        private readonly ClaimRecorder $claims,
        private readonly DisputeLedger $ledger,
        private readonly UserStatusService $users,
        private readonly MediaAttachmentService $media,
        private readonly ModerationActionLog $moderation,
    ) {}

    /**
     * @param  list<string>  $evidence  ULIDs of the claimant's own `evidence` uploads
     */
    public function open(User $claimant, PlayerTag $tag, string $reason, array $evidence = [], ?string $note = null): DisputeResultData
    {
        Gate::forUser($claimant)->authorize('open', CocAccountDispute::class);
        $reason = $this->requiredText($reason, 'reason');
        $note = $this->text($note, 'note');
        $this->checkEvidenceCount($evidence);

        return DB::transaction(function () use ($claimant, $tag, $reason, $evidence, $note): DisputeResultData {
            $rows = CocAccount::query()->where('tag_normalized', $tag->bare())->orderBy('id')->lockForUpdate()->get();
            $held = $rows->first(fn (CocAccount $row): bool => in_array($row->status, AttachAccountService::HOLDING, true));

            $refusal = match (true) {
                $rows->contains(fn (CocAccount $row): bool => $row->status === CocAccountStatus::Suspended) => DisputeRefusal::TagSuspended,
                $held === null || $held->user_id === null => DisputeRefusal::NotHeld,
                $held->user_id === $claimant->id => DisputeRefusal::OwnAccount,
                $held->status === CocAccountStatus::Disputed => DisputeRefusal::AlreadyDisputed,
                default => null,
            };
            if ($refusal !== null) {
                return DisputeResultData::refused($refusal);
            }

            // Counted under the claimant's lock, so two submits cannot both pass the limit.
            $this->users->lockAccounts([$claimant->id]);
            // Reopening the same tag right after withdrawing would restart the holder's window.
            $recentlyWithdrawn = CocAccountDispute::query()->where('claimant_id', $claimant->id)->where('tag_normalized', $tag->bare())
                ->where('status', DisputeStatus::Withdrawn)->where('decided_at', '>=', Date::now()->subDays((int) config('coc.disputes.reopen_cooldown_days')))->exists();
            if ($recentlyWithdrawn) {
                return DisputeResultData::refused(DisputeRefusal::RecentlyWithdrawn);
            }
            if (($limit = $this->limitRefusal($claimant)) !== null) {
                if ($limit === DisputeRefusal::Barred) {
                    $this->claims->securityEvent('coc.dispute_denied_bar', $claimant, $tag);
                }

                return DisputeResultData::refused($limit);
            }

            /** @var CocAccount $held */
            $dispute = (new CocAccountDispute)->forceFill([
                'coc_account_id' => $held->id,
                'tag_normalized' => $tag->bare(),
                'claimant_id' => $claimant->id,
                'current_holder_id' => $held->user_id,
                'reason' => $reason,
                'status' => DisputeStatus::Open,
                'awaiting_since' => Date::now(),
            ]);
            $dispute->save();
            $this->addEvidence($dispute, $claimant, DisputeParty::Claimant, $evidence, $note);

            $held->forceFill(['status' => CocAccountStatus::Disputed])->save();

            $this->ledger->record($claimant, AuditAction::CocDisputeOpened, $dispute, null, ['status' => DisputeStatus::Open->value], [
                'holder_user_id' => $held->user_id,
                'evidence' => count($evidence),
            ]);
            $this->claims->securityEvent('coc.dispute_opened', $claimant, $tag, ['dispute' => $dispute->ulid]);
            CocAccountDisputeOpened::dispatch($dispute->id, $claimant->id, $held->user_id);

            return new DisputeResultData($dispute->ulid, DisputeStatus::Open);
        });
    }

    /**
     * A party's answer (specs/13 §5 3b, or what an admin asked for): the holder while the dispute
     * waits on them, the claimant when an admin asked them. Either way it goes to the admins.
     *
     * @param  list<string>  $evidence
     */
    public function respond(User $user, string $disputeUlid, string $statement, array $evidence = []): DisputeResultData
    {
        $dispute = $this->ownDispute($user, $disputeUlid);
        Gate::forUser($user)->authorize('respond', $dispute);
        $statement = $this->requiredText($statement, 'statement');
        $this->checkEvidenceCount($evidence);

        return DB::transaction(function () use ($user, $dispute, $statement, $evidence): DisputeResultData {
            $dispute = $this->lock($dispute);
            $party = $dispute->current_holder_id === $user->id ? DisputeParty::Holder : DisputeParty::Claimant;
            $waiting = $party === DisputeParty::Holder
                ? [DisputeStatus::Open, DisputeStatus::AwaitingHolder]
                : [DisputeStatus::AwaitingClaimant];

            if (! $dispute->status->isActive()) {
                return DisputeResultData::refused(DisputeRefusal::Closed, $dispute->ulid);
            }
            if (! in_array($dispute->status, $waiting, true)) {
                return DisputeResultData::refused(DisputeRefusal::NotYourTurn, $dispute->ulid);
            }

            $before = $dispute->status;
            $this->addEvidence($dispute, $user, $party, $evidence, $statement);
            $dispute->forceFill(['status' => DisputeStatus::AwaitingAdmin, 'awaiting_since' => Date::now()])->save();
            $this->ledger->record($user, AuditAction::CocDisputeResponded, $dispute, ['status' => $before->value], ['status' => DisputeStatus::AwaitingAdmin->value], [
                'party' => $party->value,
                'evidence' => count($evidence),
            ]);

            return new DisputeResultData($dispute->ulid, DisputeStatus::AwaitingAdmin);
        });
    }

    public function withdraw(User $claimant, string $disputeUlid): DisputeResultData
    {
        $dispute = $this->ownDispute($claimant, $disputeUlid);
        Gate::forUser($claimant)->authorize('withdraw', $dispute);

        return DB::transaction(function () use ($claimant, $dispute): DisputeResultData {
            [$dispute] = $this->lockForChange($dispute);
            if (! $dispute->status->isActive()) {
                return DisputeResultData::refused(DisputeRefusal::Closed, $dispute->ulid);
            }
            // Once the holder has answered, the claim goes to a decision: withdrawing then would
            // dodge the bar that denials build up (specs/13 §5 guardrails).
            if ($dispute->status !== DisputeStatus::Open) {
                return DisputeResultData::refused(DisputeRefusal::NotYourTurn, $dispute->ulid);
            }

            $this->ledger->close($dispute, DisputeStatus::Withdrawn, 'claimant', $claimant, context: ['by' => 'claimant']);

            return new DisputeResultData($dispute->ulid, DisputeStatus::Withdrawn);
        });
    }

    /**
     * The holder gives the tag up (specs/13 §5 3c): their row is released and the claimant gets it
     * verified, recorded as a voluntary release (owner decision 2026-10-02, P2-03). No takeover
     * notice: the holder did it.
     */
    public function release(User $holder, string $disputeUlid): DisputeResultData
    {
        $dispute = $this->ownDispute($holder, $disputeUlid);
        Gate::forUser($holder)->authorize('release', $dispute);

        return DB::transaction(function () use ($holder, $dispute): DisputeResultData {
            [$dispute, $held] = $this->lockForChange($dispute);
            if (! $dispute->status->isActive() || $held === null) {
                return DisputeResultData::refused(DisputeRefusal::Closed, $dispute->ulid);
            }
            // A banned, suspended or leaving claimant cannot receive a tag.
            if (! $dispute->claimant()->firstOrFail()->allowsAccountWrites()) {
                return DisputeResultData::refused(DisputeRefusal::ClaimantUnavailable, $dispute->ulid);
            }

            $held->forceFill([
                'user_id' => null,
                'status' => CocAccountStatus::Released,
                'verified_at' => null,
                'verification_method' => null,
                'is_featured' => false,
            ])->save();

            $granted = $this->grantToClaimant($dispute, $held, null);
            $this->ledger->close($dispute, DisputeStatus::ResolvedTransfer, 'holder', $holder, context: [
                'by' => 'holder_release',
                'from_user_id' => $holder->id,
                'to_user_id' => $dispute->claimant_id,
            ]);
            $this->recount([$holder->id, $dispute->claimant_id]);
            CocAccountVerified::dispatch($granted['account']->id, $dispute->claimant_id, $granted['claim']);

            return new DisputeResultData($dispute->ulid, DisputeStatus::ResolvedTransfer);
        });
    }

    /**
     * An admin's decision (specs/13 §5 steps 4–6). The note is internal and required.
     */
    public function decide(User $admin, string $disputeUlid, DisputeDecision $decision, string $note): DisputeResultData
    {
        // Without the ability every ULID is a 404, so the answer says nothing about which exist.
        if (Gate::forUser($admin)->denies(StaffAbility::ResolveDisputes->value)) {
            throw (new ModelNotFoundException)->setModel(CocAccountDispute::class, [$disputeUlid]);
        }
        $dispute = CocAccountDispute::query()->where('ulid', $disputeUlid)->firstOrFail();
        Gate::forUser($admin)->authorize('decide', $dispute);
        $note = $this->requiredText($note, 'note');

        return DB::transaction(function () use ($admin, $dispute, $decision, $note): DisputeResultData {
            [$dispute, $held] = $this->lockForChange($dispute);
            if (! $dispute->status->isActive()) {
                return DisputeResultData::refused(DisputeRefusal::Closed, $dispute->ulid);
            }

            // The holder gets their window first (specs/13 §5 steps 3–4): moving the tag waits until
            // the dispute is with the admins. A deny or a question can come at any time.
            $moves = in_array($decision, [DisputeDecision::Transfer, DisputeDecision::Suspend], true);
            if ($moves && $dispute->status !== DisputeStatus::AwaitingAdmin) {
                return DisputeResultData::refused(DisputeRefusal::NotYourTurn, $dispute->ulid);
            }
            if ($decision === DisputeDecision::Transfer && ! $dispute->claimant()->firstOrFail()->allowsAccountWrites()) {
                return DisputeResultData::refused(DisputeRefusal::ClaimantUnavailable, $dispute->ulid);
            }

            $dispute->forceFill(['assigned_admin_id' => $dispute->assigned_admin_id ?? $admin->id, 'decision_note' => $note]);

            return match ($decision) {
                DisputeDecision::Transfer => $this->transfer($admin, $dispute, $held, $note),
                DisputeDecision::Deny => $this->deny($admin, $dispute, $note),
                DisputeDecision::Suspend => $this->suspend($admin, $dispute, $held, $note),
                DisputeDecision::AskClaimant => $this->ask($admin, $dispute, DisputeParty::Claimant),
                DisputeDecision::AskHolder => $this->ask($admin, $dispute, DisputeParty::Holder),
            };
        });
    }

    /**
     * The hourly sweep (specs/13 §5 3d and guardrails): a dispute the holder has not answered in
     * `holder_response_days` goes to the admins (non-response alone never transfers); one that has
     * waited `claimant_inactive_days` on the claimant is withdrawn.
     *
     * @return array{escalated: int, withdrawn: int}
     */
    public function sweep(): array
    {
        $escalated = 0;
        $withdrawn = 0;
        $holderDeadline = Date::now()->subDays((int) config('coc.disputes.holder_response_days'));
        $claimantDeadline = Date::now()->subDays((int) config('coc.disputes.claimant_inactive_days'));

        $due = CocAccountDispute::query()->whereIn('status', [DisputeStatus::Open, DisputeStatus::AwaitingHolder])
            ->where('awaiting_since', '<=', $holderDeadline)->pluck('id');
        foreach ($due as $id) {
            $escalated += DB::transaction(function () use ($id, $holderDeadline): int {
                $dispute = CocAccountDispute::query()->lockForUpdate()->whereKey($id)->first();
                if ($dispute === null || ! in_array($dispute->status, [DisputeStatus::Open, DisputeStatus::AwaitingHolder], true) || $dispute->awaiting_since->greaterThan($holderDeadline)) {
                    return 0;
                }

                $before = $dispute->status;
                $dispute->forceFill(['status' => DisputeStatus::AwaitingAdmin, 'awaiting_since' => Date::now(), 'escalated_at' => Date::now()])->save();
                $this->ledger->record(null, AuditAction::CocDisputeEscalated, $dispute, ['status' => $before->value], ['status' => DisputeStatus::AwaitingAdmin->value], ['by' => 'no_holder_response']);

                return 1;
            });
        }

        $stale = CocAccountDispute::query()->where('status', DisputeStatus::AwaitingClaimant)->where('awaiting_since', '<=', $claimantDeadline)->pluck('id');
        foreach ($stale as $id) {
            $withdrawn += DB::transaction(function () use ($id, $claimantDeadline): int {
                // Closing touches the holder's row, so lock as verification does: rows, then the dispute.
                $unlocked = CocAccountDispute::query()->whereKey($id)->first();
                if ($unlocked === null) {
                    return 0;
                }
                [$dispute] = $this->lockForChange($unlocked);
                if ($dispute->status !== DisputeStatus::AwaitingClaimant || $dispute->awaiting_since->greaterThan($claimantDeadline)) {
                    return 0;
                }

                $this->ledger->close($dispute, DisputeStatus::Withdrawn, 'sweep', null, context: ['by' => 'claimant_inactive']);

                return 1;
            });
        }

        return ['escalated' => $escalated, 'withdrawn' => $withdrawn];
    }

    private function transfer(User $admin, CocAccountDispute $dispute, ?CocAccount $held, string $note): DisputeResultData
    {
        // The holder keeps their row, unverified, as after a token takeover (specs/13 §3.1).
        $held?->forceFill(['status' => CocAccountStatus::Unverified, 'verified_at' => null, 'verification_method' => null, 'is_featured' => false])->save();

        $source = $held ?? $dispute->account()->firstOrFail();
        $granted = $this->grantToClaimant($dispute, $source, $admin);
        $this->ledger->close($dispute, DisputeStatus::ResolvedTransfer, 'admin', $admin, $admin, [
            'from_user_id' => $dispute->current_holder_id,
            'to_user_id' => $dispute->claimant_id,
        ]);
        $this->moderation->record($admin, ModerationActionType::TransferOwnership, 'coc_account_dispute', $dispute->id, $dispute->current_holder_id, ReasonCode::FalseOwnership, $note, [
            'from_user_id' => $dispute->current_holder_id,
            'to_user_id' => $dispute->claimant_id,
            'coc_account_id' => $granted['account']->id,
        ]);
        $this->recount(array_values(array_filter([$dispute->current_holder_id, $dispute->claimant_id])));

        CocAccountVerified::dispatch($granted['account']->id, $dispute->claimant_id, $granted['claim']);
        if ($dispute->current_holder_id !== null) {
            CocAccountOwnershipTransferred::dispatch($granted['account']->id, $dispute->current_holder_id, $dispute->claimant_id, VerificationMethod::Admin);
        }

        return new DisputeResultData($dispute->ulid, DisputeStatus::ResolvedTransfer);
    }

    private function deny(User $admin, CocAccountDispute $dispute, string $note): DisputeResultData
    {
        // A banned holder cannot win (specs/13 §9): transfer or suspend instead.
        if ($dispute->holder?->status === UserStatus::Banned) {
            return DisputeResultData::refused(DisputeRefusal::HolderCannotKeep, $dispute->ulid);
        }

        $this->ledger->close($dispute, DisputeStatus::ResolvedDenied, 'admin', $admin, $admin);
        $this->moderation->record($admin, ModerationActionType::Dismiss, 'coc_account_dispute', $dispute->id, $dispute->claimant_id, ReasonCode::FalseOwnership, $note);

        // A third denial is a sanctionable false claim (specs/13 §5); the report reason joins with P3-06.
        $denied = CocAccountDispute::query()->where('claimant_id', $dispute->claimant_id)->where('status', DisputeStatus::ResolvedDenied)->count();
        if ($denied > (int) config('coc.disputes.bar_after_denials')) {
            $claimant = $dispute->claimant()->firstOrFail();
            $this->claims->securityEvent('coc.dispute_false_claim', $claimant, PlayerTag::from($dispute->tag_normalized), ['dispute' => $dispute->ulid, 'denied' => $denied, 'actor' => $admin->ulid], fromRequest: false);
        }

        return new DisputeResultData($dispute->ulid, DisputeStatus::ResolvedDenied);
    }

    private function suspend(User $admin, CocAccountDispute $dispute, ?CocAccount $held, string $note): DisputeResultData
    {
        // Both parties look fraudulent: neither gets the tag (specs/13 §5).
        $held?->forceFill(['status' => CocAccountStatus::Suspended, 'is_featured' => false])->save();

        $this->ledger->close($dispute, DisputeStatus::ResolvedSuspended, 'admin', $admin, $admin);
        $this->moderation->record($admin, ModerationActionType::Suspend, 'coc_account_dispute', $dispute->id, $dispute->current_holder_id, ReasonCode::FalseOwnership, $note, [
            'coc_account_id' => $dispute->coc_account_id,
        ]);
        if ($dispute->current_holder_id !== null) {
            $this->recount([$dispute->current_holder_id]);
        }

        return new DisputeResultData($dispute->ulid, DisputeStatus::ResolvedSuspended);
    }

    private function ask(User $admin, CocAccountDispute $dispute, DisputeParty $party): DisputeResultData
    {
        $before = $dispute->status;
        $status = $party === DisputeParty::Claimant ? DisputeStatus::AwaitingClaimant : DisputeStatus::AwaitingHolder;
        $dispute->forceFill(['status' => $status, 'awaiting_since' => Date::now()])->save();

        $this->ledger->record($admin, AuditAction::CocDisputeInfoRequested, $dispute, ['status' => $before->value], ['status' => $status->value], ['party' => $party->value]);
        CocAccountDisputeInfoRequested::dispatch($dispute->id, $party);

        return new DisputeResultData($dispute->ulid, $status);
    }

    /**
     * The claimant's row, verified by `admin`, with a succeeded claim row (method `dispute`).
     *
     * @return array{account: CocAccount, claim: int}
     */
    private function grantToClaimant(CocAccountDispute $dispute, CocAccount $source, ?User $admin): array
    {
        $claimant = $dispute->claimant()->firstOrFail();
        $account = $this->rows->grant($claimant, $source);
        $hasFeatured = CocAccount::query()->where('user_id', $claimant->id)->where('is_featured', true)->whereKeyNot($account->id)->exists();

        $account->forceFill([
            'status' => CocAccountStatus::Verified,
            'verified_at' => Date::now(),
            'verification_method' => VerificationMethod::Admin,
            'is_featured' => ! $hasFeatured,
        ])->save();

        // The request is the holder's or the admin's: none of its device data belongs on the claimant's row.
        $claim = $this->claims->record($claimant, PlayerTag::from($dispute->tag_normalized), $account->id, ClaimStatus::Succeeded, method: $admin === null ? ClaimMethod::Dispute : ClaimMethod::Admin, fromRequest: false);

        return ['account' => $account, 'claim' => $claim->id];
    }

    /**
     * The dispute, then every row of its tag in id order, then both users in id order: the order
     * verification locks in, so a token arriving during a decision waits for it.
     *
     * @return array{0: CocAccountDispute, 1: CocAccount|null}
     */
    private function lockForChange(CocAccountDispute $dispute): array
    {
        /** @var Collection<int, CocAccount> $rows */
        $rows = CocAccount::query()->where('tag_normalized', $dispute->tag_normalized)->orderBy('id')->lockForUpdate()->get();
        $dispute = $this->lock($dispute);
        $this->users->lockAccounts(array_values(array_filter([$dispute->claimant_id, $dispute->current_holder_id])));

        // The holder's row, if it still holds the tag for them.
        $held = $rows->first(fn (CocAccount $row): bool => $row->id === $dispute->coc_account_id
            && $row->user_id === $dispute->current_holder_id
            && $row->status === CocAccountStatus::Disputed);

        return [$dispute, $held];
    }

    private function lock(CocAccountDispute $dispute): CocAccountDispute
    {
        return CocAccountDispute::query()->lockForUpdate()->findOrFail($dispute->id);
    }

    /**
     * A dispute the user is a party to; anyone else's is a 404 (specs/04 §3).
     */
    private function ownDispute(User $user, string $ulid): CocAccountDispute
    {
        return CocAccountDispute::query()->where('ulid', $ulid)
            ->where(fn ($q) => $q->where('claimant_id', $user->id)->orWhere('current_holder_id', $user->id))
            ->firstOrFail();
    }

    private function limitRefusal(User $claimant): ?DisputeRefusal
    {
        $running = CocAccountDispute::query()->active()->where('claimant_id', $claimant->id)->count();
        if ($running >= (int) config('coc.disputes.max_open_per_user')) {
            return DisputeRefusal::TooManyOpen;
        }

        // A denial, or a withdrawal for not answering an admin, counts toward the bar.
        $recentDenials = CocAccountDispute::query()->where('claimant_id', $claimant->id)
            ->where(fn ($q) => $q->where('status', DisputeStatus::ResolvedDenied)->orWhere(fn ($w) => $w->where('status', DisputeStatus::Withdrawn)->where('closed_by', 'sweep')))
            ->where('decided_at', '>=', Date::now()->subDays((int) config('coc.disputes.bar_days')))->count();

        return $recentDenials >= (int) config('coc.disputes.bar_after_denials') ? DisputeRefusal::Barred : null;
    }

    /**
     * Appends one submission; each upload must be the submitter's own, in `evidence`, and usable.
     *
     * @param  list<string>  $media
     */
    private function addEvidence(CocAccountDispute $dispute, User $user, DisputeParty $party, array $media, ?string $note): void
    {
        // The cap is per party over the whole dispute (specs/10: 3 per item), not per round.
        $already = collect($dispute->evidence)->where('party', $party->value)->pluck('media')->flatten()->unique()->count();
        if ($already + count(array_unique($media)) > (int) config('coc.disputes.evidence_max')) {
            throw ValidationException::withMessages(['evidence' => 'Attach at most '.config('coc.disputes.evidence_max').' images in a dispute.']);
        }

        foreach (array_values(array_unique($media)) as $position => $ulid) {
            $this->media->attach($user, $ulid, MediaCollection::Evidence, $dispute, $position, 'evidence');
        }

        if ($media === [] && $note === null) {
            return;
        }

        $dispute->forceFill(['evidence' => [...$dispute->evidence, [
            'party' => $party->value,
            'note' => $note,
            'media' => array_values(array_unique($media)),
            'at' => Date::now()->toIso8601String(),
        ]]])->save();
    }

    /**
     * @param  list<string>  $evidence
     */
    private function checkEvidenceCount(array $evidence): void
    {
        if (count(array_unique($evidence)) > (int) config('coc.disputes.evidence_max')) {
            throw ValidationException::withMessages(['evidence' => 'Attach at most '.config('coc.disputes.evidence_max').' images.']);
        }
    }

    private function text(?string $value, string $field): ?string
    {
        $value = $value === null ? '' : trim($value);
        $max = (int) config('coc.disputes.text_max');

        if (mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => "Keep it under {$max} characters."]);
        }

        return $value === '' ? null : $value;
    }

    private function requiredText(string $value, string $field): string
    {
        return $this->text($value, $field) ?? throw ValidationException::withMessages([$field => 'Write a few words about it.']);
    }

    /**
     * @param  list<int>  $userIds
     */
    private function recount(array $userIds): void
    {
        foreach (array_unique($userIds) as $userId) {
            $this->users->syncVerifiedAccounts(
                $userId,
                CocAccount::query()->where('user_id', $userId)->whereIn('status', AttachAccountService::HOLDING)->count(),
            );
        }
    }
}
