<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;

/**
 * Which accounts a base may credit (P3-01), for the Bases module: the user's own held rows
 * (`verified` or `disputed`).
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
}
