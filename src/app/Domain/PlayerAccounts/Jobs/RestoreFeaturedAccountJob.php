<?php

namespace App\Domain\PlayerAccounts\Jobs;

use App\Domain\Auth\Services\UserStatusService;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Support\FeaturedAccount;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Gives a user their featured account back when the in-transaction fallback found every candidate
 * row locked by another transaction (P2-14). Idempotent: a user who has one by now is left alone.
 */
class RestoreFeaturedAccountJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $uniqueFor = 60;

    public function __construct(public readonly int $userId) {}

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(UserStatusService $users): void
    {
        DB::transaction(function () use ($users): void {
            // Rows, then the user: the order verification and detach take.
            CocAccount::query()->where('user_id', $this->userId)->whereIn('status', CocAccountStatus::HOLDING)
                ->orderBy('id')->lockForUpdate()->pluck('id');
            $users->lockAccounts([$this->userId]);

            FeaturedAccount::restore($this->userId);
        });
    }
}
