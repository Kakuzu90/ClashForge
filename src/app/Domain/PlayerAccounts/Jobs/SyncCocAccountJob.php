<?php

namespace App\Domain\PlayerAccounts\Jobs;

use App\Domain\CocIntegration\Enums\SyncResourceType;
use App\Domain\CocIntegration\Services\SyncSchedule;
use App\Domain\PlayerAccounts\Services\AccountSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One account's background sync (specs/20 §2). API trouble never throws here: the service
 * reschedules the account itself, so the queue's retries only cover our own failures (the
 * database, a bug).
 */
class SyncCocAccountJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 55;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    // specs/09 §6: a slow run never doubles up.
    public int $uniqueFor = 60;

    public function __construct(public readonly int $accountId)
    {
        $this->onQueue((string) config('coc.sync.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->accountId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("coc-account-sync:{$this->accountId}"))->dontRelease()->expireAfter($this->timeout + 5)];
    }

    public function handle(AccountSyncService $sync): void
    {
        $started = Date::now();
        $outcome = $sync->sync($this->accountId);
        $ms = (int) $started->diffInMilliseconds(Date::now());

        Log::log($ms > 10_000 ? 'warning' : 'info', 'coc.account_synced', ['account_id' => $this->accountId, 'outcome' => $outcome->value, 'duration_ms' => $ms]);
    }

    /**
     * Out of tries on our own failure: count it against the account, so a poison account backs off
     * and eventually freezes instead of coming back every claim (specs/20 §4 rule 4).
     */
    public function failed(?Throwable $e): void
    {
        Log::error('coc.account_sync_failed', ['account_id' => $this->accountId, 'error' => $e === null ? null : $e::class]);
        app(SyncSchedule::class)->recordFailure(SyncResourceType::CocAccount, $this->accountId);
    }
}
