<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\Auth\Services\UserStatusService;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Queries\AccountReadModel;
use App\Domain\Search\Contracts\SearchSource;
use App\Domain\Search\Contracts\TagLookup;
use App\Domain\Search\Data\SearchSectionData;
use App\Domain\Search\Data\SourceQuery;
use App\Domain\Search\Enums\SearchType;
use App\Domain\Search\Services\TextQuery;
use App\Domain\Users\Services\SearchVisibility;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * CoC accounts in search (specs/17 §2), as the profile's list PlayerCards: the in-game name through the existing
 * `to_tsvector('simple', ign)` index, ranked by text match, then trophies. A Town Hall named in
 * the text filters too. Only held accounts (`verified`, `disputed`) of a listed owner who shows
 * their accounts and is not suspended, banned or leaving; unverified accounts are never search
 * data. The exact-tag lookup (FR-SEARCH-2) uses the same rule, so a hidden account's tag answers
 * like an unknown one.
 */
class AccountSearchSource implements SearchSource, TagLookup
{
    private const IGN_VECTOR = "to_tsvector('simple', coc_accounts.ign)";

    public function __construct(
        private readonly SearchVisibility $visibility,
        private readonly UserStatusService $statuses,
        private readonly AccountReadModel $accounts,
    ) {}

    public function type(): SearchType
    {
        return SearchType::Accounts;
    }

    public function search(SourceQuery $query, ?User $viewer): ?SearchSectionData
    {
        if (! $query->hasTerm()) {
            return null;
        }

        $inner = $this->listed($viewer)
            ->when($query->thLevel !== null, fn (Builder $q) => $q->where('coc_accounts.th_level', $query->thLevel))
            ->whereRaw(self::IGN_VECTOR.' @@ '.TextQuery::NAMES, [$query->term])
            ->select(['coc_accounts.id'])
            ->selectRaw('coalesce(coc_accounts.trophies, 0) AS sort_trophies')
            ->selectRaw('ts_rank_cd('.self::IGN_VECTOR.', '.TextQuery::NAMES.', 32) AS rank', [$query->term]);
        $hits = DB::query()->fromSub($inner, 'hits');

        if ($query->cursor !== null) {
            $hits->whereRaw('(rank, sort_trophies, id) < (CAST(? AS real), CAST(? AS integer), ?)', [
                $query->cursor->values[0] ?? '0', $query->cursor->values[1] ?? '0', $query->cursor->id,
            ]);
        }

        $rows = array_values($hits->orderByDesc('rank')->orderByDesc('sort_trophies')->orderByDesc('id')->limit($query->limit + 1)->get()->all());
        $more = count($rows) > $query->limit;
        $rows = array_slice($rows, 0, $query->limit);
        $last = $rows === [] ? null : $rows[array_key_last($rows)];

        return new SearchSectionData(
            type: SearchType::Accounts,
            items: $this->accounts->cards(array_map(fn (stdClass $row): int => (int) $row->id, $rows)),
            hasMore: $more,
            nextCursor: $last === null ? null : $query->next($more, [
                is_float($last->rank) ? sprintf('%.9g', $last->rank) : (string) $last->rank,
                (string) $last->sort_trophies,
            ], (int) $last->id),
        );
    }

    public function findAccount(PlayerTag $tag, ?User $viewer): ?string
    {
        $ulid = $this->listed($viewer)->where('coc_accounts.tag_normalized', $tag->bare())->value('coc_accounts.ulid');

        return is_string($ulid) ? $ulid : null;
    }

    /**
     * The name vectors are an expression index kept by Postgres; nothing to rebuild.
     */
    public function reindex(): int
    {
        return 0;
    }

    private function listed(?User $viewer): Builder
    {
        return CocAccount::query()->toBase()
            ->whereIn('coc_accounts.status', array_map(fn (CocAccountStatus $status): string => $status->value, CocAccountStatus::HOLDING))
            ->whereIn('coc_accounts.user_id', $this->visibility->listedAccountOwners($viewer))
            ->whereNotIn('coc_accounts.user_id', $this->statuses->hiddenAuthorIds());
    }
}
