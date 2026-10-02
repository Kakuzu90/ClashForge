<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\Auth\Services\UserLookupService;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Services\PlayerLookup;
use App\Domain\PlayerAccounts\Data\AttachResultData;
use App\Domain\PlayerAccounts\Data\CocPlayerPreviewData;
use App\Domain\PlayerAccounts\Enums\AttachBlock;
use App\Domain\PlayerAccounts\Enums\AttachOutcome;
use App\Domain\PlayerAccounts\Enums\ClaimFailureReason;
use App\Domain\PlayerAccounts\Enums\ClaimStatus;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Events\CocAccountAttached;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Support\AccountRows;
use App\Domain\PlayerAccounts\Support\ClaimLimits;
use App\Domain\PlayerAccounts\Support\ClaimRecorder;
use App\Domain\Users\Services\PrivacyPolicyResolver;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Attaching a tag as an unverified claim (specs/13 §3 steps 1–5, FR-COC-1/3/6). `preview` is the
 * "Is this you?" step and writes nothing; `attach` creates the row. Both refuse a tag another user
 * holds (verified or disputed): the way in is a token (VerifyOwnershipService::verifyTag) or a
 * dispute (specs/13 §4).
 * With the API down, a recent answer from the cache is enough to attach (specs/13 §9).
 */
class AttachAccountService
{
    /** A row in one of these statuses holds the tag (specs/13 §2). */
    public const HOLDING = [CocAccountStatus::Verified, CocAccountStatus::Disputed];

    public function __construct(
        private readonly PlayerLookup $players,
        private readonly ClaimLimits $limits,
        private readonly ClaimRecorder $claims,
        private readonly AccountRows $rows,
        private readonly UserLookupService $users,
        private readonly PrivacyPolicyResolver $privacy,
    ) {}

    /**
     * Why this user may not attach, for the page to explain instead of failing on submit; null
     * when they may (CocAccountPolicy::attach).
     */
    public function block(User $user): ?AttachBlock
    {
        return match (true) {
            Gate::forUser($user)->allows('attach', CocAccount::class) => null,
            ! $user->hasVerifiedEmail() => AttachBlock::EmailUnverified,
            default => AttachBlock::AccountBlocked,
        };
    }

    public function preview(User $user, PlayerTag $tag): AttachResultData
    {
        Gate::forUser($user)->authorize('attach', CocAccount::class);

        if (($own = $this->rows->own($user, $tag)) !== null) {
            return new AttachResultData(AttachOutcome::AlreadyAttached, $tag->value, accountUlid: $own->ulid);
        }

        if (($wait = $this->limits->attach($user, $tag)) !== null) {
            if ($this->limits->firstRefusal($user, 'attach', $wait)) {
                $this->claims->securityEvent('coc.attach_attempt', $user, $tag, ['outcome' => AttachOutcome::RateLimited->value]);
            }

            return new AttachResultData(AttachOutcome::RateLimited, $tag->value, retryAfter: $wait);
        }

        $lookup = $this->players->find($tag);

        if ($lookup->status === CocLookupStatus::NotFound) {
            return new AttachResultData(AttachOutcome::NotFound, $tag->value);
        }

        if ($lookup->player === null) {
            return new AttachResultData(AttachOutcome::Unavailable, $tag->value, retryAfter: $lookup->retryAfter);
        }

        $player = CocPlayerPreviewData::fromPlayer($lookup->player);
        $holder = $this->holder($user, $tag);

        return $holder !== null
            ? new AttachResultData(AttachOutcome::VerifiedElsewhere, $tag->value, player: $player, holderUsername: $this->holderName($user, $holder))
            : new AttachResultData(AttachOutcome::Ready, $tag->value, player: $player);
    }

    public function attach(User $user, PlayerTag $tag): AttachResultData
    {
        Gate::forUser($user)->authorize('attach', CocAccount::class);

        if (($own = $this->rows->own($user, $tag)) !== null) {
            return new AttachResultData(AttachOutcome::AlreadyAttached, $tag->value, accountUlid: $own->ulid);
        }

        if (($wait = $this->limits->attach($user, $tag)) !== null) {
            if ($this->limits->firstRefusal($user, 'attach', $wait)) {
                $this->claims->record($user, $tag, null, ClaimStatus::Failed, ClaimFailureReason::RateLimited);
                $this->claims->securityEvent('coc.attach_attempt', $user, $tag, ['outcome' => AttachOutcome::RateLimited->value]);
            }

            return new AttachResultData(AttachOutcome::RateLimited, $tag->value, retryAfter: $wait);
        }

        if (($holder = $this->holder($user, $tag)) !== null) {
            return $this->refuse($user, $tag, AttachOutcome::VerifiedElsewhere, ClaimFailureReason::AlreadyClaimed, holder: $this->holderName($user, $holder));
        }

        $lookup = $this->players->find($tag);

        if ($lookup->status === CocLookupStatus::NotFound) {
            return $this->refuse($user, $tag, AttachOutcome::NotFound, ClaimFailureReason::NotFound);
        }

        $player = $lookup->player;

        if ($player === null) {
            return $this->refuse($user, $tag, AttachOutcome::Unavailable, ClaimFailureReason::ApiError, retryAfter: $lookup->retryAfter);
        }

        try {
            $account = DB::transaction(fn (): CocAccount => $this->rows->createOrReuse($user, $tag, $player));
        } catch (UniqueConstraintViolationException) {
            // A second request for the same tag won the race.
            $own = $this->rows->own($user, $tag);

            return new AttachResultData(AttachOutcome::AlreadyAttached, $tag->value, accountUlid: $own?->ulid);
        }

        CocAccountAttached::dispatch($account->id, $user->id);
        $this->claims->securityEvent('coc.attach_attempt', $user, $tag, ['outcome' => AttachOutcome::Attached->value, 'account' => $account->ulid]);
        $this->flagAnomaly($user, $tag);

        return new AttachResultData(
            AttachOutcome::Attached,
            $tag->value,
            player: CocPlayerPreviewData::fromPlayer($player),
            accountUlid: $account->ulid,
        );
    }

    private function refuse(User $user, PlayerTag $tag, AttachOutcome $outcome, ClaimFailureReason $reason, ?string $holder = null, ?int $retryAfter = null): AttachResultData
    {
        $this->claims->record($user, $tag, null, ClaimStatus::Failed, $reason);
        $this->claims->securityEvent('coc.attach_attempt', $user, $tag, ['outcome' => $outcome->value]);

        return new AttachResultData($outcome, $tag->value, holderUsername: $holder, retryAfter: $retryAfter);
    }

    /**
     * Another user's row holding the tag (specs/13 §4). A holder whose website account is gone
     * still holds the tag.
     */
    private function holder(User $user, PlayerTag $tag): ?CocAccount
    {
        return CocAccount::query()
            ->with('user:id,username')
            ->where('tag_normalized', $tag->bare())
            ->whereIn('status', self::HOLDING)
            ->where(fn ($q) => $q->where('user_id', '!=', $user->id)->orWhereNull('user_id'))
            ->first();
    }

    /**
     * The current holder's username for a conflict card shown again later: privacy and bans apply
     * on every view, not only when the tag was looked up.
     */
    public function holderUsername(User $viewer, PlayerTag $tag): ?string
    {
        $holder = $this->holder($viewer, $tag);

        return $holder === null ? null : $this->holderName($viewer, $holder);
    }

    /**
     * The holder's username for the conflict card, only where their profile would show this tag:
     * a listed account whose profile this viewer can see, with connected accounts shown. Otherwise
     * null, and the card says "another Clash Commons player" (owner decision 2026-10-02, P2-11).
     */
    private function holderName(User $viewer, CocAccount $holder): ?string
    {
        $username = $holder->user?->username;
        $owner = $username === null ? null : $this->users->findListed($username);

        if ($owner === null || ! $this->privacy->canView($viewer, $owner) || ! $this->privacy->settingsFor($owner->id)->showCocAccounts) {
            return null;
        }

        return $owner->username;
    }

    /**
     * Badge farming shows up as many attached tags (specs/23 §2): flagged at the threshold and
     * above, at most once per `anomaly_reflag_hours` per user.
     */
    private function flagAnomaly(User $user, PlayerTag $tag): void
    {
        $count = CocAccount::query()->where('user_id', $user->id)->count();

        if ($count >= (int) config('coc.accounts.anomaly_accounts')
            && Cache::add("coc-attach-anomaly:{$user->id}", true, (int) config('coc.accounts.anomaly_reflag_hours') * 3600)) {
            $this->claims->securityEvent('coc.attach_anomaly', $user, $tag, ['accounts' => $count]);
        }
    }
}
