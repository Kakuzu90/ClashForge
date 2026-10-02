<?php

namespace App\Domain\PlayerAccounts\Queries;

use App\Domain\PlayerAccounts\Data\OwnCocAccountData;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Models\User;

/**
 * Reads of a user's own accounts. Every query is scoped to the owner, so another user's ulid
 * finds nothing (specs/04 §3, 404 first).
 */
class AccountReadModel
{
    /**
     * The owner's rows still tied to them, featured first, then newest. Released rows are gone
     * from their list (specs/13 §6).
     *
     * @return list<OwnCocAccountData>
     */
    public function own(User $user): array
    {
        return array_values(CocAccount::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', CocAccountStatus::Released)
            ->orderByDesc('is_featured')->orderByDesc('id')
            ->get()
            ->map($this->data(...))
            ->all());
    }

    public function ownRow(User $user, string $ulid): ?OwnCocAccountData
    {
        $account = CocAccount::query()->where('ulid', $ulid)->where('user_id', $user->id)->first();

        return $account === null ? null : $this->data($account);
    }

    private function data(CocAccount $account): OwnCocAccountData
    {
        return new OwnCocAccountData(
            ulid: $account->ulid,
            tag: $account->tag,
            name: $account->ign,
            status: $account->status,
            statusLabel: $account->status->label(),
            townHallLevel: $account->th_level,
            featured: $account->is_featured,
        );
    }
}
