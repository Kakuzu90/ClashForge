<?php

namespace App\Domain\PlayerAccounts\Queries;

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Services\MediaReadService;
use App\Domain\PlayerAccounts\Data\DisputeEvidenceImageData;
use App\Domain\PlayerAccounts\Data\PartyDisputeData;
use App\Domain\PlayerAccounts\Data\PartySubmissionData;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeParty;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Enums\PartyDisputeOutcome;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;

/**
 * The parties' side of a dispute (P2-16): their dispute page, and the conflict card's link to
 * it. A dispute the viewer is not a party to is a 404 (specs/04 §3).
 */
class DisputePartyQuery
{
    public function __construct(private readonly MediaReadService $media) {}

    public function forParty(User $viewer, string $ulid): PartyDisputeData
    {
        $dispute = CocAccountDispute::query()->where('ulid', $ulid)
            ->where(fn ($q) => $q->where('claimant_id', $viewer->id)->orWhere('current_holder_id', $viewer->id))
            ->firstOrFail();
        Gate::forUser($viewer)->authorize('view', $dispute);

        $role = $dispute->claimant_id === $viewer->id ? DisputeParty::Claimant : DisputeParty::Holder;
        $claimant = $role === DisputeParty::Claimant;
        $active = $dispute->status->isActive();
        $waitingOn = match ($dispute->status) {
            DisputeStatus::Open, DisputeStatus::AwaitingHolder => DisputeParty::Holder->value,
            DisputeStatus::AwaitingClaimant => DisputeParty::Claimant->value,
            DisputeStatus::AwaitingAdmin => 'admin',
            default => null,
        };
        $ownTurn = $waitingOn === $role->value;
        $outcome = PartyDisputeOutcome::for($dispute->status, $dispute->closed_by, $role);

        // The viewer's own row of the tag, if they hold one now.
        $own = CocAccount::query()->where('tag_normalized', $dispute->tag_normalized)->where('user_id', $viewer->id)
            ->whereIn('status', [CocAccountStatus::Verified, CocAccountStatus::Disputed])->first();
        $heldRow = $role === DisputeParty::Holder && $own !== null && $own->id === $dispute->coc_account_id && $own->status === CocAccountStatus::Disputed;

        $submissions = $this->submissions($dispute, $role);
        $used = collect($dispute->evidence)->where('party', $role->value)->pluck('media')->flatten()->unique()->count();

        return new PartyDisputeData(
            ulid: $dispute->ulid,
            tag: '#'.$dispute->tag_normalized,
            role: $role,
            status: $dispute->status,
            statusLabel: $dispute->status->label(),
            waitingOn: $waitingOn,
            deadline: $ownTurn ? $this->deadline($dispute) : null,
            openedAt: $dispute->created_at->toIso8601String(),
            closedAt: $dispute->decided_at?->toIso8601String(),
            outcome: $outcome,
            outcomeLabel: $outcome?->label(),
            accountUlid: $own?->ulid,
            submissions: $submissions,
            evidenceLeft: max(0, (int) config('coc.disputes.evidence_max') - $used),
            canRespond: $ownTurn && Gate::forUser($viewer)->allows('respond', $dispute),
            canWithdraw: $claimant && $dispute->status === DisputeStatus::Open && Gate::forUser($viewer)->allows('withdraw', $dispute),
            canRelease: $active && $heldRow && Gate::forUser($viewer)->allows('release', $dispute),
            canVerify: $active && $heldRow && Gate::forUser($viewer)->allows('verify', $own),
            withdrawCountsTowardBar: $claimant && Date::now()->lt($dispute->created_at->addHours((int) config('coc.disputes.early_withdraw_hours'))),
        );
    }

    /**
     * The claimant's running dispute over this tag, for the conflict card's link.
     */
    public function runningFor(User $claimant, PlayerTag $tag): ?string
    {
        $ulid = CocAccountDispute::query()->active()->where('claimant_id', $claimant->id)->where('tag_normalized', $tag->bare())->value('ulid');

        return is_string($ulid) ? $ulid : null;
    }

    /**
     * When the current wait on the viewer ends: the holder's window before it goes to the admins,
     * or the claimant's before the dispute is withdrawn (specs/13 §5).
     */
    private function deadline(CocAccountDispute $dispute): ?string
    {
        $days = match ($dispute->status) {
            DisputeStatus::Open, DisputeStatus::AwaitingHolder => (int) config('coc.disputes.holder_response_days'),
            DisputeStatus::AwaitingClaimant => (int) config('coc.disputes.claimant_inactive_days'),
            default => null,
        };

        return $days === null ? null : $dispute->awaiting_since->addDays($days)->toIso8601String();
    }

    /**
     * The viewer's own statements and images. The uploader may see their own evidence, so these
     * thumbnails are not an evidence access to audit (owner decision 2026-10-06, P2-16).
     *
     * @return list<PartySubmissionData>
     */
    private function submissions(CocAccountDispute $dispute, DisputeParty $role): array
    {
        $entries = collect($dispute->evidence)->where('party', $role->value)->values();
        /** @var list<string> $ulids */
        $ulids = $entries->pluck('media')->flatten()->unique()->values()->all();
        $thumbs = $this->media->readyVariantUrlsByUlid($ulids, VariantName::Thumb, $dispute, MediaCollection::Evidence);

        $submissions = $role === DisputeParty::Claimant
            ? [new PartySubmissionData(note: $dispute->reason, images: [], at: $dispute->created_at->toIso8601String(), opening: true)]
            : [];
        foreach ($entries as $entry) {
            $submissions[] = new PartySubmissionData(
                note: $entry['note'],
                images: array_map(fn (string $ulid): DisputeEvidenceImageData => new DisputeEvidenceImageData($ulid, null, $thumbs[$ulid] ?? null), $entry['media']),
                at: $entry['at'],
                opening: false,
                removed: (int) ($entry['removed'] ?? 0),
            );
        }

        return $submissions;
    }
}
