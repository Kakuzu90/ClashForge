<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;

/**
 * Whether a dispute's page may release its tag (specs/13 §2, P2-25): the dispute ended suspended,
 * its row is still `suspended`, and no later dispute over the tag exists. A later one means the tag
 * was released and taken up again since, so a suspension now in force is that dispute's, not this one's.
 */
final class SuspendedTag
{
    public static function releasableFrom(CocAccountDispute $dispute, ?CocAccount $row = null): bool
    {
        $row ??= $dispute->account;

        return $dispute->status === DisputeStatus::ResolvedSuspended
            && $row !== null && $row->status === CocAccountStatus::Suspended
            && CocAccountDispute::query()->where('tag_normalized', $dispute->tag_normalized)->where('id', '>', $dispute->id)->doesntExist();
    }
}
