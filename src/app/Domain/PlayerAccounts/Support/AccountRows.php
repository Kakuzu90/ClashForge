<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\CocIntegration\Data\PlayerData;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\PlayerAccounts\Enums\ClaimStatus;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Models\User;

/**
 * Finding and creating a user's row for a tag, shared by attach and by token verification of a tag
 * the user has not attached yet (specs/13 §4 path A).
 */
final class AccountRows
{
    public function __construct(private readonly ClaimRecorder $claims) {}

    /**
     * @phpstan-impure A concurrent attach may create the row between two calls.
     */
    public function own(User $user, PlayerTag $tag): ?CocAccount
    {
        return CocAccount::query()->where('user_id', $user->id)->where('tag_normalized', $tag->bare())->first();
    }

    /**
     * Run inside a transaction. A released row for the tag is reused, so its snapshot history
     * stays continuous (specs/13 §6); otherwise a new row. Either way the user gets a pending claim.
     */
    public function createOrReuse(User $user, PlayerTag $tag, PlayerData $player): CocAccount
    {
        $account = CocAccount::query()
            ->where('tag_normalized', $tag->bare())
            ->where('status', CocAccountStatus::Released)
            ->whereNull('user_id')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first() ?? new CocAccount;

        $account->fill(AccountGameData::attributes($player));
        $account->forceFill([
            'tag' => $tag->value,
            'tag_normalized' => $tag->bare(),
            'user_id' => $user->id,
            'status' => CocAccountStatus::Unverified,
            'verified_at' => null,
            'verification_method' => null,
            'is_featured' => false,
        ])->save();

        $this->claims->record($user, $tag, $account->id, ClaimStatus::Pending);

        return $account;
    }
}
