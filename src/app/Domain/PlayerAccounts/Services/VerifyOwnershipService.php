<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\Audit\Data\AuditActorData;
use App\Domain\Audit\Data\AuditEntryData;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Auth\Services\UserStatusService;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Enums\TokenVerificationStatus;
use App\Domain\CocIntegration\Services\PlayerLookup;
use App\Domain\CocIntegration\Services\TokenVerifier;
use App\Domain\PlayerAccounts\Data\VerifyResultData;
use App\Domain\PlayerAccounts\Enums\ClaimFailureReason;
use App\Domain\PlayerAccounts\Enums\ClaimStatus;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\VerificationMethod;
use App\Domain\PlayerAccounts\Enums\VerifyOutcome;
use App\Domain\PlayerAccounts\Events\CocAccountOwnershipTransferred;
use App\Domain\PlayerAccounts\Events\CocAccountVerified;
use App\Domain\PlayerAccounts\Exceptions\TagSuspended;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Support\AccountRows;
use App\Domain\PlayerAccounts\Support\ClaimLimits;
use App\Domain\PlayerAccounts\Support\ClaimRecorder;
use App\Domain\PlayerAccounts\Support\DisputeLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use SensitiveParameter;

/**
 * Ownership by in-game token (specs/13 §3.1, FR-COC-5/6). The token is checked outside any
 * transaction and goes nowhere else (specs/09 §9). On `ok`, one transaction locks every row of the
 * tag, so two valid tokens seconds apart are serialised and the later one wins (specs/13 §9):
 *
 * - another user's holding row drops to `unverified` and keeps its owner, who can verify again;
 *   it loses its featured flag (owner decision 2026-10-02);
 * - this row becomes `verified` by `api_token`, and the user's featured account if they have none;
 * - claim rows, both users' verified counts and one `audit_logs` entry are written with it.
 *
 * Any other answer writes a failed claim row and changes nothing else. A token also ends the tag's
 * running disputes (specs/13 §3.1 step 8, §5 3a): the holder's own denies the claim, anyone else's
 * resolves it. A suspended tag takes no token (specs/13 §2).
 */
class VerifyOwnershipService
{
    public function __construct(
        private readonly TokenVerifier $tokens,
        private readonly ClaimLimits $limits,
        private readonly ClaimRecorder $claims,
        private readonly UserStatusService $users,
        private readonly AuditLogger $audit,
        private readonly PlayerLookup $players,
        private readonly AccountRows $rows,
        private readonly DisputeLedger $disputes,
    ) {}

    /**
     * A tag staff suspended in a dispute takes no verification until they release it (specs/13 §2).
     */
    public static function suspended(PlayerTag $tag): bool
    {
        return CocAccount::query()->where('tag_normalized', $tag->bare())->where('status', CocAccountStatus::Suspended)->exists();
    }

    /**
     * Token verification of a tag the user may not have attached, the conflict card's first path
     * (specs/13 §4 A): someone else holds the tag, so attaching is refused, but a current in-game
     * token still wins (specs/13 rule 3). With the user's own row it is the same as verify().
     * Otherwise the row is created and promoted in one transaction, only after the token passed,
     * so a failed attempt leaves nothing but its claim row.
     */
    public function verifyTag(User $user, PlayerTag $tag, #[SensitiveParameter] string $token): VerifyResultData
    {
        Gate::forUser($user)->authorize('attach', CocAccount::class);

        if (($own = $this->rows->own($user, $tag)) !== null) {
            return $this->verify($user, $own->ulid, $token);
        }

        if (self::suspended($tag)) {
            return $this->fail($user, $tag, null, VerifyOutcome::TagSuspended, ClaimFailureReason::AlreadyClaimed);
        }

        if (($wait = $this->limits->verify($user)) !== null) {
            return $this->fail($user, $tag, null, VerifyOutcome::RateLimited, ClaimFailureReason::RateLimited, $wait);
        }

        $answer = $this->tokens->verify($tag, $token);

        if ($answer->status !== TokenVerificationStatus::Ok) {
            return $this->failFor($user, $tag, null, $answer->status, $answer->retryAfter);
        }

        $lookup = $this->players->find($tag);
        $player = $lookup->player;

        if ($player === null) {
            $status = $lookup->status === CocLookupStatus::NotFound ? TokenVerificationStatus::NotFound : TokenVerificationStatus::Unavailable;

            return $this->failFor($user, $tag, null, $status, $lookup->retryAfter);
        }

        try {
            try {
                return DB::transaction(fn (): VerifyResultData => $this->promote($user, $tag, $this->rows->createOrReuse($user, $tag, $player)));
            } catch (UniqueConstraintViolationException) {
                // A parallel attach of the same tag by this user created the row first.
                $own = $this->rows->own($user, $tag) ?? throw (new ModelNotFoundException)->setModel(CocAccount::class);

                return $this->promote($user, $tag, $own);
            }
        } catch (TagSuspended) {
            return $this->fail($user, $tag, null, VerifyOutcome::TagSuspended, ClaimFailureReason::AlreadyClaimed);
        }
    }

    public function verify(User $user, string $accountUlid, #[SensitiveParameter] string $token): VerifyResultData
    {
        $account = CocAccount::query()->where('ulid', $accountUlid)->where('user_id', $user->id)->firstOrFail();
        Gate::forUser($user)->authorize('verify', $account);
        $tag = PlayerTag::from($account->tag);

        if (self::suspended($tag)) {
            return $this->fail($user, $tag, $account, VerifyOutcome::TagSuspended, ClaimFailureReason::AlreadyClaimed);
        }

        if (($wait = $this->limits->verify($user)) !== null) {
            return $this->fail($user, $tag, $account, VerifyOutcome::RateLimited, ClaimFailureReason::RateLimited, $wait);
        }

        $answer = $this->tokens->verify($tag, $token);

        if ($answer->status !== TokenVerificationStatus::Ok) {
            return $this->failFor($user, $tag, $account, $answer->status, $answer->retryAfter);
        }

        try {
            return $this->promote($user, $tag, $account);
        } catch (TagSuspended) {
            return $this->fail($user, $tag, $account, VerifyOutcome::TagSuspended, ClaimFailureReason::AlreadyClaimed);
        }
    }

    private function failFor(User $user, PlayerTag $tag, ?CocAccount $account, TokenVerificationStatus $status, ?int $retryAfter): VerifyResultData
    {
        return match ($status) {
            TokenVerificationStatus::Ok, TokenVerificationStatus::Invalid => $this->fail($user, $tag, $account, VerifyOutcome::InvalidToken, ClaimFailureReason::InvalidToken),
            TokenVerificationStatus::NotFound => $this->fail($user, $tag, $account, VerifyOutcome::NotFound, ClaimFailureReason::NotFound),
            TokenVerificationStatus::Unavailable => $this->fail($user, $tag, $account, VerifyOutcome::Unavailable, ClaimFailureReason::ApiError, $retryAfter),
        };
    }

    private function promote(User $user, PlayerTag $tag, CocAccount $account): VerifyResultData
    {
        /** @var array{0: CocAccount, 1: list<CocAccount>} $outcome */
        $outcome = DB::transaction(function () use ($user, $tag, $account): array {
            // Every row of the tag, in id order so concurrent verifications lock in the same order.
            $rows = CocAccount::query()->where('tag_normalized', $tag->bare())->orderBy('id')->lockForUpdate()->get();
            $mine = $rows->firstWhere('id', $account->id);

            // Authoritative: an admin may have suspended the tag since the pre-check.
            if ($rows->contains(fn (CocAccount $row): bool => $row->status === CocAccountStatus::Suspended)) {
                throw new TagSuspended;
            }

            // Again on the locked row: it may have changed since the check. A double submit finds it
            // verified already and answers the same; anything else is refused.
            if ($mine === null) {
                throw (new ModelNotFoundException)->setModel(CocAccount::class, [$account->ulid]);
            }
            if ($mine->user_id === $user->id && $mine->status === CocAccountStatus::Verified) {
                return [$mine, []];
            }
            Gate::forUser($user)->authorize('verify', $mine);

            // Every user whose featured flag or count may change, in id order: two verifications
            // by one user for different tags, or two crossing supersedes, then run one at a time.
            $holders = $rows->filter(fn (CocAccount $row): bool => $row->id !== $mine->id && in_array($row->status, AttachAccountService::HOLDING, true));
            $this->users->lockAccounts([$user->id, ...$holders->pluck('user_id')->filter()->values()->all()]);

            $superseded = [];
            foreach ($holders as $row) {
                $row->forceFill([
                    'status' => CocAccountStatus::Unverified,
                    'verified_at' => null,
                    'verification_method' => null,
                    'is_featured' => false,
                ])->save();
                // The holder's own claims only: a reused row also carries earlier users' history.
                CocAccountClaim::query()->where('coc_account_id', $row->id)->where('user_id', $row->user_id)
                    ->where('status', ClaimStatus::Pending)->update(['status' => ClaimStatus::Superseded]);
                $superseded[] = $row;
            }

            // Another row of this user's: a holder re-verifying a featured row keeps it featured.
            $hasFeatured = CocAccount::query()->where('user_id', $user->id)->where('is_featured', true)->whereKeyNot($mine->id)->exists();
            $statusBefore = $mine->status;
            $mine->forceFill([
                'status' => CocAccountStatus::Verified,
                'verified_at' => Date::now(),
                'verification_method' => VerificationMethod::ApiToken,
                'is_featured' => ! $hasFeatured,
            ])->save();

            CocAccountClaim::query()->where('coc_account_id', $mine->id)->where('user_id', $user->id)
                ->where('status', ClaimStatus::Pending)->update(['status' => ClaimStatus::Succeeded]);
            $claim = $this->claims->record($user, $tag, $mine->id, ClaimStatus::Succeeded);
            $this->disputes->closeOnVerification($tag, $user);

            $this->recount($user->id);
            foreach ($superseded as $row) {
                if ($row->user_id !== null) {
                    $this->recount($row->user_id);
                }
            }

            $previous = array_values(array_filter(array_map(fn (CocAccount $row): ?int => $row->user_id, $superseded)));
            $this->audit->record(new AuditEntryData(
                actor: new AuditActorData(id: $user->id, role: $user->role->value),
                action: AuditAction::CocAccountVerified,
                subject: AuditSubject::CocAccount,
                subjectId: $mine->id,
                before: ['status' => $statusBefore->value, 'verified_user_ids' => $previous],
                after: ['status' => CocAccountStatus::Verified->value, 'verified_user_ids' => [$user->id]],
                context: ['tag' => $tag->value, 'method' => VerificationMethod::ApiToken->value],
            ));

            CocAccountVerified::dispatch($mine->id, $user->id, $claim->id);
            foreach ($previous as $fromUserId) {
                CocAccountOwnershipTransferred::dispatch($mine->id, $fromUserId, $user->id, VerificationMethod::ApiToken);
            }

            return [$mine, $superseded];
        });

        [$mine, $superseded] = $outcome;

        foreach ($superseded as $row) {
            $this->claims->securityEvent('coc.ownership_superseded', $user, $tag, ['account' => $mine->ulid, 'previous_account' => $row->ulid]);
        }

        return new VerifyResultData(
            VerifyOutcome::Verified,
            $mine->ulid,
            superseded: $superseded !== [],
            featured: $mine->is_featured,
        );
    }

    private function fail(User $user, PlayerTag $tag, ?CocAccount $account, VerifyOutcome $outcome, ClaimFailureReason $reason, ?int $retryAfter = null): VerifyResultData
    {
        $throttled = $reason === ClaimFailureReason::RateLimited;

        // Past the limit only the first refusal of the window is written (specs/11 §3).
        if (! $throttled || $this->limits->firstRefusal($user, 'verify', (int) $retryAfter)) {
            $this->claims->record($user, $tag, $account?->id, ClaimStatus::Failed, $reason);
            $this->claims->securityEvent('coc.verification_failed', $user, $tag, ['account' => $account?->ulid, 'reason' => $reason->value]);
        }

        return new VerifyResultData($outcome, $account?->ulid, retryAfter: $retryAfter);
    }

    private function recount(int $userId): void
    {
        $this->users->syncVerifiedAccounts(
            $userId,
            CocAccount::query()->where('user_id', $userId)->whereIn('status', AttachAccountService::HOLDING)->count(),
        );
    }
}
