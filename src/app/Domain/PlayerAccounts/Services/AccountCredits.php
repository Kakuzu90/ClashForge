<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\PlayerAccounts\Data\CreditedAccountData;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;

/**
 * Which accounts a base may credit (P3-01), for the Bases module: the user's own held rows
 * (`verified` or `disputed`). Also what a feed card shows of a credit, and the Town Hall a
 * signed-in feed defaults to (P3-03).
 */
class AccountCredits
{
    /**
     * The held row with `$ulid`, or, with no ulid, the featured one, else the newest. Null when the
     * user holds none, or `$ulid` is not one of theirs.
     */
    public function creditableId(int $userId, ?string $ulid): ?int
    {
        $id = CocAccount::query()->where('user_id', $userId)->whereIn('status', CocAccountStatus::HOLDING)
            ->when($ulid !== null, fn ($query) => $query->where('ulid', $ulid))
            ->orderByDesc('is_featured')->orderByDesc('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Which of `$accountIds` the user still holds, so Bases can drop the credit of the rest
     * (specs/08 §3.2).
     *
     * @param  array<int, int>  $accountIds
     * @return array<int, int>
     */
    public function heldIds(int $userId, array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        return CocAccount::query()->where('user_id', $userId)->whereIn('status', CocAccountStatus::HOLDING)->whereKey($accountIds)
            ->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * Credited accounts for a list of bases, in one query, keyed by account id. A row no longer
     * held (released, suspended) is left out, so its credit is not shown.
     *
     * @param  list<int>  $accountIds
     * @return array<int, CreditedAccountData>
     */
    public function creditsFor(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        return CocAccount::query()->whereKey(array_values(array_unique($accountIds)))->whereIn('status', CocAccountStatus::HOLDING)
            ->get(['id', 'ulid', 'ign', 'th_level'])
            ->mapWithKeys(fn (CocAccount $account): array => [$account->id => new CreditedAccountData($account->ulid, $account->ign, $account->th_level)])
            ->all();
    }

    /**
     * The Town Hall of the user's featured held account (else their newest held one), or null.
     */
    public function featuredThLevel(int $userId): ?int
    {
        $level = CocAccount::query()->where('user_id', $userId)->whereIn('status', CocAccountStatus::HOLDING)->whereNotNull('th_level')
            ->orderByDesc('is_featured')->orderByDesc('id')
            ->value('th_level');

        return $level === null ? null : (int) $level;
    }
}
