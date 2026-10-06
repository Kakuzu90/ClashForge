<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\Auth\Services\UserActivityReader;
use App\Domain\Clans\Services\ClanDirectory;
use App\Domain\CocIntegration\Data\PlayerData;
use App\Domain\CocIntegration\Data\PlayerLookupResult;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Enums\CocPriority;
use App\Domain\CocIntegration\Enums\SyncResourceType;
use App\Domain\CocIntegration\Enums\SyncTier;
use App\Domain\CocIntegration\Services\PlayerLookup;
use App\Domain\CocIntegration\Services\SyncSchedule;
use App\Domain\Notifications\Data\InAppMessageData;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\Notifier;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\SnapshotSource;
use App\Domain\PlayerAccounts\Enums\SyncOutcome;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Support\AccountGameData;
use App\Domain\PlayerAccounts\Support\AccountProgression;
use App\Domain\PlayerAccounts\Support\SyncTierRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Background sync of one account (specs/09 §6, FR-COC-10). Only verified and disputed accounts
 * are synced; a sync never changes `status` (specs/09 §7, specs/23 §5). A 404 counts toward the
 * stale display; an API failure backs off; trouble on our side (open circuit, budget, keys)
 * postpones without counting against the account.
 *
 * A manual refresh (P2-20) goes through `apply()` with `source: manual`, and may also take the
 * owner's unverified row, which is synced by hand only: its data is stored, but it gets no
 * snapshot, no schedule and no 404 count.
 */
class AccountSyncService
{
    // The holder still holds a disputed tag (specs/13 §2), so it keeps syncing.
    public const SYNCED = [CocAccountStatus::Verified, CocAccountStatus::Disputed];

    // API failures that count against the account; the rest are ours to wait out.
    private const ACCOUNT_FAILURES = [CocFailureReason::ServerError, CocFailureReason::Timeout, CocFailureReason::Malformed];

    public function __construct(
        private readonly PlayerLookup $lookup,
        private readonly SyncSchedule $schedule,
        private readonly UserActivityReader $activity,
        private readonly Notifier $notifier,
        private readonly ClanDirectory $clans,
    ) {}

    public function sync(int $accountId, SnapshotSource $source = SnapshotSource::Scheduled): SyncOutcome
    {
        $account = CocAccount::query()->find($accountId);

        if ($account === null || ! $this->accepts($account, $source)) {
            $this->schedule->stop(SyncResourceType::CocAccount, $accountId);

            return SyncOutcome::Skipped;
        }

        return $this->apply($accountId, $this->lookup->find(PlayerTag::from($account->tag), CocPriority::Background), $source);
    }

    /**
     * One lookup's answer, applied to the account under its lock.
     */
    public function apply(int $accountId, PlayerLookupResult $result, SnapshotSource $source): SyncOutcome
    {
        return match ($result->status) {
            CocLookupStatus::Found => $this->store($accountId, $result->player, $source),
            CocLookupStatus::NotFound => $this->notFound($accountId, $source),
            CocLookupStatus::Unavailable => $this->unavailable($accountId, $result->failure, $result->retryAfter, $source),
        };
    }

    /**
     * Starts the schedule once a verification brought fresh data, with that data as the first
     * snapshot (`source: verification`).
     */
    public function startAfterVerification(int $accountId): void
    {
        $account = DB::transaction(function () use ($accountId): ?CocAccount {
            $account = CocAccount::query()->lockForUpdate()->find($accountId);

            if ($account === null || ! $this->synced($account)) {
                return null;
            }

            $account->forceFill(['api_sync_failures' => 0])->save();
            AccountProgression::snapshot($account, SnapshotSource::Verification);

            return $account;
        });

        if ($account !== null) {
            $this->schedule->start(SyncResourceType::CocAccount, $account->id, $this->tier($account));
        }
    }

    public function tier(CocAccount $account): SyncTier
    {
        return SyncTierRules::for(
            $account->is_featured,
            $account->user_id === null ? null : $this->activity->lastActiveAt($account->user_id),
            $account->last_viewed_at,
        );
    }

    private function store(int $accountId, ?PlayerData $player, SnapshotSource $source): SyncOutcome
    {
        if ($player === null) {
            return $this->unavailable($accountId, CocFailureReason::Malformed, null, $source);
        }

        /** @var array{0: CocAccount|null, 1: bool} $stored */
        $stored = DB::transaction(function () use ($accountId, $player, $source): array {
            // Re-read under the lock: the account may have changed hands or status meanwhile.
            $account = CocAccount::query()->lockForUpdate()->find($accountId);

            if ($account === null || ! $this->accepts($account, $source)) {
                return [null, false];
            }

            $next = AccountGameData::attributes($player);
            $tracked = $this->synced($account);
            $changed = AccountProgression::changed($account, $next) || ($tracked && ! $account->snapshots()->exists());

            $account->fill([...$next, 'clan_id' => $player->clan === null ? null : $this->clans->ensure($player->clan)])->forceFill(['api_sync_failures' => 0])->save();

            // Snapshots start at verification: an unverified row's history is not kept.
            if ($changed && $tracked) {
                AccountProgression::snapshot($account, $source);
            }

            return [$account, $changed];
        });

        [$account, $changed] = $stored;

        if ($account === null) {
            $this->schedule->stop(SyncResourceType::CocAccount, $accountId);

            return SyncOutcome::Skipped;
        }

        if ($this->synced($account)) {
            $this->schedule->recordSuccess(SyncResourceType::CocAccount, $accountId, $this->tier($account));
        }

        return $changed ? SyncOutcome::Changed : SyncOutcome::Unchanged;
    }

    private function notFound(int $accountId, SnapshotSource $source): SyncOutcome
    {
        $threshold = (int) config('coc.sync.not_found_stale');

        $account = DB::transaction(function () use ($accountId, $threshold, $source): ?CocAccount {
            $account = CocAccount::query()->lockForUpdate()->find($accountId);

            if ($account === null || ! $this->accepts($account, $source)) {
                return null;
            }

            if (! $this->synced($account)) {
                return $account;
            }

            $account->forceFill(['api_sync_failures' => $account->api_sync_failures + 1])->save();

            // Once, on the sync that makes it stale (specs/16 §2 "Account not found for 3 syncs").
            // In the same transaction: if the notice cannot be written, the count rolls back too and
            // the retry sends it, rather than counting past the threshold in silence.
            if ($account->api_sync_failures === $threshold && ($owner = User::query()->find($account->user_id)) !== null) {
                $this->notifier->send($owner, new InAppMessageData(NotificationType::CocAccountNotFound, ['tag' => $account->tag, 'name' => $account->ign]));
            }

            return $account;
        });

        if ($account === null) {
            $this->schedule->stop(SyncResourceType::CocAccount, $accountId);

            return SyncOutcome::Skipped;
        }

        if (! $this->synced($account)) {
            return SyncOutcome::NotFound;
        }

        $stale = $account->api_sync_failures >= $threshold;
        $this->schedule->recordFailure(SyncResourceType::CocAccount, $accountId, freeze: $stale);
        Log::warning('coc.sync_not_found', ['account' => $account->ulid, 'failures' => $account->api_sync_failures]);

        return SyncOutcome::NotFound;
    }

    private function unavailable(int $accountId, ?CocFailureReason $reason, ?int $retryAfter, SnapshotSource $source): SyncOutcome
    {
        $failed = in_array($reason, self::ACCOUNT_FAILURES, true);

        // A manual refresh's unverified row has no schedule to back off.
        if ($source === SnapshotSource::Manual) {
            $account = CocAccount::query()->find($accountId);

            if ($account === null || ! $this->synced($account)) {
                return $failed ? SyncOutcome::Failed : SyncOutcome::Postponed;
            }
        }

        if ($failed) {
            $this->schedule->recordFailure(SyncResourceType::CocAccount, $accountId);

            return SyncOutcome::Failed;
        }

        $this->schedule->postpone(SyncResourceType::CocAccount, $accountId, $retryAfter ?? (int) config('coc.sync.backoff_base'));

        return SyncOutcome::Postponed;
    }

    private function synced(CocAccount $account): bool
    {
        return $account->user_id !== null && in_array($account->status, self::SYNCED, true);
    }

    /**
     * Synced in the background, or the owner's unverified row on a manual refresh (specs/09 §6).
     */
    private function accepts(CocAccount $account, SnapshotSource $source): bool
    {
        return $this->synced($account)
            || ($source === SnapshotSource::Manual && $account->user_id !== null && $account->status === CocAccountStatus::Unverified);
    }
}
