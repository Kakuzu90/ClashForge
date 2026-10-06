<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Enums\CocPriority;
use App\Domain\CocIntegration\Services\PlayerLookup;
use App\Domain\PlayerAccounts\Data\RefreshResultData;
use App\Domain\PlayerAccounts\Enums\RefreshOutcome;
use App\Domain\PlayerAccounts\Enums\SnapshotSource;
use App\Domain\PlayerAccounts\Enums\SyncOutcome;
use App\Domain\PlayerAccounts\Jobs\SyncCocAccountJob;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Support\RefreshCooldown;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

/**
 * The owner's manual refresh (FR-COC-9, specs/09 §6, P2-20). The owner waits at most
 * `coc.sync.manual_timeout` seconds for a fresh answer, which is stored like a sync's with
 * `source: manual`; past that the job takes over. The limits are spent only when the API was
 * asked and answered: an open circuit, an empty budget or an API error gives them back
 * (specs/09 §7).
 */
class AccountRefreshService
{
    public function __construct(
        private readonly PlayerLookup $lookup,
        private readonly AccountSyncService $sync,
    ) {}

    public function refresh(User $user, string $accountUlid): RefreshResultData
    {
        $account = CocAccount::query()->where('ulid', $accountUlid)->where('user_id', $user->id)->firstOrFail();
        Gate::forUser($user)->authorize('refresh', $account);

        if (($refused = RefreshCooldown::take($user->id, $account->id)) !== null) {
            return $refused;
        }

        $outcome = null;

        try {
            $outcome = $this->ask($account, $accountUlid);
        } finally {
            // Kept only when the API was asked and answered; anything else, an exception included,
            // stored nothing and gives the refresh back.
            if (! in_array($outcome, [RefreshOutcome::Updated, RefreshOutcome::Background, RefreshOutcome::NotFound], true)) {
                RefreshCooldown::giveBack($user->id, $account->id);
            }
        }

        return new RefreshResultData($outcome);
    }

    private function ask(CocAccount $account, string $accountUlid): RefreshOutcome
    {
        $result = $this->lookup->find(PlayerTag::from($account->tag), CocPriority::Interactive, fresh: true, timeout: (int) config('coc.sync.manual_timeout'));

        if ($result->status === CocLookupStatus::Unavailable) {
            if ($result->failure !== CocFailureReason::Deadline) {
                return RefreshOutcome::Unavailable;
            }

            SyncCocAccountJob::dispatch($account->id, SnapshotSource::Manual);

            return RefreshOutcome::Background;
        }

        return match ($this->sync->apply($account->id, $result, SnapshotSource::Manual)) {
            SyncOutcome::Changed, SyncOutcome::Unchanged => RefreshOutcome::Updated,
            SyncOutcome::NotFound => RefreshOutcome::NotFound,
            // An answer that could not be read is stored nowhere (specs/23 §5).
            SyncOutcome::Failed, SyncOutcome::Postponed => RefreshOutcome::Unavailable,
            // Released, suspended or passed on while the API answered.
            SyncOutcome::Skipped => throw (new ModelNotFoundException)->setModel(CocAccount::class, [$accountUlid]),
        };
    }
}
