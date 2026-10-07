<?php

namespace App\Domain\Search\Contracts;

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Models\User;

/**
 * The exact player-tag lookup behind FR-SEARCH-2, implemented by PlayerAccounts.
 */
interface TagLookup
{
    /**
     * The ulid of the account with this tag when this viewer may find it in search, else null;
     * an account hidden from search answers exactly like an unknown tag.
     */
    public function findAccount(PlayerTag $tag, ?User $viewer): ?string;
}
