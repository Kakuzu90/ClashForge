<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Services\MediaReadService;
use App\Domain\PlayerAccounts\Data\DisputeClaimData;
use App\Domain\PlayerAccounts\Data\DisputeDecisionOptionData;
use App\Domain\PlayerAccounts\Data\DisputeEvidenceData;
use App\Domain\PlayerAccounts\Data\DisputeEvidenceImageData;
use App\Domain\PlayerAccounts\Data\DisputePartyData;
use App\Domain\PlayerAccounts\Data\DisputePriorData;
use App\Domain\PlayerAccounts\Data\DisputeReviewData;
use App\Domain\PlayerAccounts\Data\DisputeReviewResult;
use App\Domain\PlayerAccounts\Data\DisputeSnapshotData;
use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use App\Domain\PlayerAccounts\Enums\DisputeParty;
use App\Domain\PlayerAccounts\Enums\DisputeRefusal;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Models\CocAccountSnapshot;
use App\Domain\PlayerAccounts\Support\DisputeLedger;
use App\Domain\PlayerAccounts\Support\DisputeRank;
use App\Domain\PlayerAccounts\Support\DisputeStake;
use App\Domain\PlayerAccounts\Support\SuspendedTag;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

/**
 * The admin review page of one dispute (specs/13 §5 step 4, P2-17). Without `resolve-disputes`,
 * or with a stake in the tag (DisputeStake), every ULID is a 404. Evidence is private: the signed URLs are handed out here only,
 * and each view that shows any is written to `audit_logs` first (specs/13 §5 guardrails, specs/12 §9).
 */
class DisputeReviewService
{
    public function __construct(
        private readonly DisputeLedger $ledger,
        private readonly MediaReadService $media,
    ) {}

    public function review(User $admin, string $ulid): DisputeReviewResult
    {
        if (Gate::forUser($admin)->denies(StaffAbility::ResolveDisputes->value)) {
            throw (new ModelNotFoundException)->setModel(CocAccountDispute::class, [$ulid]);
        }
        $dispute = CocAccountDispute::query()->with(['claimant', 'holder'])->where('ulid', $ulid)->first();
        if ($dispute === null || Gate::forUser($admin)->denies('review', $dispute)) {
            throw (new ModelNotFoundException)->setModel(CocAccountDispute::class, [$ulid]);
        }

        /** @var User $claimant */
        $claimant = $dispute->claimant;
        // The account FK restricts deletes, so the held row is always there.
        $account = $dispute->account()->firstOrFail();
        $holder = $dispute->holder;
        $limit = (int) config('coc.disputes.review_history_limit');
        $stake = DisputeStake::tags($admin);
        $blocked = DisputeRank::blockedReason($admin, $dispute);
        // The staff writes of P2-25. `review` passed above, so only the rest is decided here, once
        // per page rather than once per evidence entry (each policy call reads the admin's stake).
        $releasable = SuspendedTag::releasableFrom($dispute, $account);
        $outranksHolder = DisputeRank::outranks($admin, $account->user);
        $removable = [
            DisputeParty::Claimant->value => DisputeRank::outranks($admin, $claimant),
            DisputeParty::Holder->value => DisputeRank::outranks($admin, $holder),
        ];
        $assigned = User::query()->withTrashed()->whereKey(array_filter([$dispute->assigned_admin_id, $dispute->decided_by]))->pluck('username', 'id');

        $data = new DisputeReviewData(
            ulid: $dispute->ulid,
            tag: '#'.$dispute->tag_normalized,
            status: $dispute->status,
            statusLabel: $dispute->status->label(),
            active: $dispute->status->isActive(),
            openedAt: $dispute->created_at->toIso8601String(),
            waitingSince: $dispute->awaiting_since->toIso8601String(),
            escalatedAt: $dispute->escalated_at?->toIso8601String(),
            decidedAt: $dispute->decided_at?->toIso8601String(),
            decidedBy: $dispute->decided_by === null ? null : ($assigned[$dispute->decided_by] ?? null),
            assignedTo: $dispute->assigned_admin_id === null ? null : ($assigned[$dispute->assigned_admin_id] ?? null),
            decisionNote: $dispute->decision_note,
            accountName: $account->ign,
            accountStatus: $account->status,
            accountTownHall: $account->th_level,
            claimant: $this->party($stake, $claimant, $dispute, $limit),
            holder: $holder === null ? null : $this->party($stake, $holder, $dispute, $limit),
            evidence: $this->evidence($admin, $dispute, $removable),
            claims: $this->claims($dispute, $limit),
            snapshots: $this->snapshots($dispute, $limit),
            decisions: $this->decisions($dispute, $blocked),
            blockedReason: $blocked,
            noteMax: (int) config('coc.disputes.text_max'),
            canReleaseTag: $releasable && $outranksHolder,
            releaseBlockedReason: $releasable && ! $outranksHolder ? 'The holder is an admin, so only a super admin can release this tag.' : null,
        );

        return new DisputeReviewResult($data, $dispute->id, $claimant, $holder);
    }

    /**
     * @param  list<string>  $stake  tags the viewing admin has a stake in
     */
    private function party(array $stake, User $user, CocAccountDispute $dispute, int $limit): DisputePartyData
    {
        $status = $user->effectiveStatus();
        $prior = CocAccountDispute::query()->whereKeyNot($dispute->id)
            ->where(fn ($q) => $q->where('claimant_id', $user->id)->orWhere('current_holder_id', $user->id))
            ->orderByDesc('id')->limit($limit)->get();

        return new DisputePartyData(
            ulid: $user->ulid,
            username: $user->username,
            roleLabel: $user->role->label(),
            statusLabel: $status->label(),
            statusTone: match ($status) {
                UserStatus::Active => 'success',
                UserStatus::Restricted => 'warning',
                UserStatus::Suspended, UserStatus::Banned => 'danger',
                UserStatus::PendingDeletion => 'neutral',
            },
            joinedAt: $user->created_at->toIso8601String(),
            verifiedAccounts: $user->verified_accounts_count,
            deleted: $user->deleted_at !== null,
            priorDisputes: array_values($prior->map(fn (CocAccountDispute $other): DisputePriorData => new DisputePriorData(
                ulid: $other->ulid,
                tag: '#'.$other->tag_normalized,
                side: $other->claimant_id === $user->id ? 'claimant' : 'holder',
                status: $other->status,
                statusLabel: $other->status->label(),
                openedAt: $other->created_at->toIso8601String(),
                // A dispute over a tag the viewer has a stake in has no admin review page for them.
                reviewable: ! in_array($other->tag_normalized, $stake, true),
            ))->all()),
        );
    }

    /**
     * The opening statement, then every submission in order. The media they cite are looked up
     * once; the view is audited before any signed URL leaves.
     *
     * @return list<DisputeEvidenceData>
     */
    /**
     * @param  array<string, bool>  $removable  by party: may this admin delete that party's images
     */
    private function evidence(User $admin, CocAccountDispute $dispute, array $removable): array
    {
        /** @var list<string> $ulids */
        $ulids = collect($dispute->evidence)->pluck('media')->flatten()->unique()->values()->all();

        if ($ulids !== []) {
            $this->ledger->record($admin, AuditAction::CocDisputeEvidenceViewed, $dispute, null, null, ['media' => $ulids]);
        }
        $full = $this->media->readyVariantUrlsByUlid($ulids, VariantName::Full, $dispute, MediaCollection::Evidence);
        $thumbs = $this->media->readyVariantUrlsByUlid($ulids, VariantName::Thumb, $dispute, MediaCollection::Evidence);

        $entries = [new DisputeEvidenceData(party: 'claimant', note: $dispute->reason, images: [], at: $dispute->created_at->toIso8601String(), opening: true)];
        foreach ($dispute->evidence as $entry) {
            $entries[] = new DisputeEvidenceData(
                party: $entry['party'],
                note: $entry['note'],
                images: array_map(fn (string $ulid): DisputeEvidenceImageData => new DisputeEvidenceImageData($ulid, $full[$ulid] ?? null, $thumbs[$ulid] ?? $full[$ulid] ?? null), $entry['media']),
                at: $entry['at'],
                opening: false,
                removed: (int) ($entry['removed'] ?? 0),
                removable: $entry['media'] !== [] && ($removable[$entry['party']] ?? false),
            );
        }

        return $entries;
    }

    /**
     * @return list<DisputeClaimData>
     */
    private function claims(CocAccountDispute $dispute, int $limit): array
    {
        $claims = CocAccountClaim::query()->where('tag_normalized', $dispute->tag_normalized)->orderByDesc('id')->limit($limit)->get();
        $names = User::query()->withTrashed()->whereKey($claims->pluck('user_id')->unique()->all())->pluck('username', 'id');

        return array_values($claims->map(fn (CocAccountClaim $claim): DisputeClaimData => new DisputeClaimData(
            username: (string) ($names[$claim->user_id] ?? ''),
            methodLabel: $claim->method->label(),
            statusLabel: $claim->status->label(),
            failureLabel: $claim->failure_reason?->label(),
            at: $claim->created_at->toIso8601String(),
        ))->all());
    }

    /**
     * The tag's snapshots across every row (history is read by tag, specs/08 §3.1), newest first,
     * each marked with what changed since the one before it.
     *
     * @return list<DisputeSnapshotData>
     */
    private function snapshots(CocAccountDispute $dispute, int $limit): array
    {
        $rows = CocAccountSnapshot::query()
            ->join('coc_accounts', 'coc_accounts.id', '=', 'coc_account_snapshots.coc_account_id')
            ->where('coc_accounts.tag_normalized', $dispute->tag_normalized)
            ->orderByDesc('coc_account_snapshots.captured_at')->orderByDesc('coc_account_snapshots.id')
            ->limit($limit + 1)
            ->get(['coc_account_snapshots.*'])
            ->values();

        $snapshots = [];
        foreach ($rows->take($limit) as $index => $snapshot) {
            $older = $rows->get($index + 1);
            $snapshots[] = new DisputeSnapshotData(
                capturedAt: $snapshot->captured_at->toIso8601String(),
                townHallLevel: $snapshot->th_level,
                clanTag: $snapshot->clan_tag,
                trophies: $snapshot->trophies,
                changes: $older === null ? [] : array_values(array_filter([
                    $older->clan_tag !== $snapshot->clan_tag ? 'clan' : null,
                    $older->th_level !== $snapshot->th_level ? 'th' : null,
                ])),
            );
        }

        return $snapshots;
    }

    /**
     * What the form offers, mirroring DisputeService::decide, which checks again under its locks.
     *
     * @return list<DisputeDecisionOptionData>
     */
    private function decisions(CocAccountDispute $dispute, ?string $blocked): array
    {
        $withAdmins = $dispute->status === DisputeStatus::AwaitingAdmin;

        return array_map(function (DisputeDecision $decision) use ($dispute, $blocked, $withAdmins): DisputeDecisionOptionData {
            $reason = $blocked ?? match ($decision) {
                DisputeDecision::Transfer => match (true) {
                    ! $withAdmins => 'Waits until the holder has answered or their time is up.',
                    $dispute->claimant?->allowsAccountWrites() !== true => DisputeRefusal::ClaimantUnavailable->label().'.',
                    default => null,
                },
                DisputeDecision::Suspend => $withAdmins ? null : 'Waits until the holder has answered or their time is up.',
                DisputeDecision::Deny => $dispute->holder?->status === UserStatus::Banned ? DisputeRefusal::HolderCannotKeep->label().'.' : null,
                DisputeDecision::AskHolder => $dispute->holder === null ? 'There is no holder to ask.' : null,
                DisputeDecision::AskClaimant => null,
            };

            return new DisputeDecisionOptionData($decision, $decision->label(), $reason === null, $reason);
        }, DisputeDecision::cases());
    }
}
