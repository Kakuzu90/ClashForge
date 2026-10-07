<?php

namespace App\Domain\Users\Services;

use App\Domain\Users\Enums\ProfileVisibility;
use App\Domain\Users\Models\PrivacySettings;
use App\Models\User;
use Illuminate\Database\Query\Builder;

/**
 * Whose content search may list (specs/17 §2, specs/11 §2), as subqueries of user ids for other
 * modules' search queries: a profile the viewer may open (`public`, or `members` when signed in)
 * with `searchable` on. Accounts also need `show_coc_accounts`. Turning `searchable` off hides
 * the player and their accounts, not their public bases (P3-05). No privacy row lists nothing.
 */
class SearchVisibility
{
    public function listedProfileOwners(?User $viewer): Builder
    {
        return PrivacySettings::query()->toBase()->select('user_id')
            ->where('searchable', true)
            ->whereIn('profile_visibility', $viewer === null
                ? [ProfileVisibility::Public->value]
                : [ProfileVisibility::Public->value, ProfileVisibility::Members->value]);
    }

    public function listedAccountOwners(?User $viewer): Builder
    {
        return $this->listedProfileOwners($viewer)->where('show_coc_accounts', true);
    }
}
