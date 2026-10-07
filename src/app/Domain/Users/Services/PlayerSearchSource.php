<?php

namespace App\Domain\Users\Services;

use App\Domain\Auth\Services\UserStatusService;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Services\MediaReadService;
use App\Domain\Search\Contracts\SearchSource;
use App\Domain\Search\Data\SearchSectionData;
use App\Domain\Search\Data\SourceQuery;
use App\Domain\Search\Enums\SearchType;
use App\Domain\Search\Services\TextQuery;
use App\Domain\Users\Data\PlayerHitData;
use App\Domain\Users\Models\Profile;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * Players in search (specs/17 §2): username, display name and bio, ranked by text match, newest
 * profile first on a tie. Lists only profiles `SearchVisibility` allows whose owner is not
 * suspended, banned or leaving.
 */
class PlayerSearchSource implements SearchSource
{
    public function __construct(
        private readonly SearchVisibility $visibility,
        private readonly UserStatusService $statuses,
        private readonly MediaReadService $media,
    ) {}

    public function type(): SearchType
    {
        return SearchType::Players;
    }

    public function search(SourceQuery $query, ?User $viewer): ?SearchSectionData
    {
        if (! $query->hasTerm()) {
            return null;
        }

        $inner = Profile::query()->toBase()
            ->join('users', 'users.id', '=', 'profiles.user_id')
            ->whereNull('users.deleted_at')
            ->whereIn('profiles.user_id', $this->visibility->listedProfileOwners($viewer))
            ->whereNotIn('profiles.user_id', $this->statuses->hiddenAuthorIds())
            ->whereRaw('profiles.search_vector @@ '.TextQuery::ANY, TextQuery::bindings($query->term))
            ->select(['profiles.id', 'users.username', 'profiles.display_name', 'profiles.bio', 'profiles.avatar_media_id'])
            ->selectRaw('ts_rank_cd(profiles.search_vector, '.TextQuery::ANY.', 32) AS rank', TextQuery::bindings($query->term));
        $hits = DB::query()->fromSub($inner, 'hits');

        if ($query->cursor !== null) {
            $rank = $query->cursor->values[0] ?? '0';
            $hits->where(fn (Builder $q) => $q
                ->whereRaw('rank < CAST(? AS real)', [$rank])
                ->orWhere(fn (Builder $q) => $q->whereRaw('rank = CAST(? AS real)', [$rank])->where('id', '<', $query->cursor->id)));
        }

        $rows = array_values($hits->orderByDesc('rank')->orderByDesc('id')->limit($query->limit + 1)->get()->all());
        $more = count($rows) > $query->limit;
        $rows = array_slice($rows, 0, $query->limit);
        $last = $rows === [] ? null : $rows[array_key_last($rows)];

        return new SearchSectionData(
            type: SearchType::Players,
            items: $this->hits($rows),
            hasMore: $more,
            nextCursor: $last === null ? null : $query->next($more, [self::exact($last->rank)], (int) $last->id),
        );
    }

    public function reindex(): int
    {
        return DB::getDriverName() === 'pgsql'
            ? DB::update('UPDATE profiles SET search_vector = profile_search_vector(user_id, display_name, bio)')
            : 0;
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<PlayerHitData>
     */
    private function hits(array $rows): array
    {
        $avatars = $this->media->readyVariantUrls(array_values(array_filter(array_map(
            fn (stdClass $row): ?int => $row->avatar_media_id === null ? null : (int) $row->avatar_media_id,
            $rows,
        ))), VariantName::Thumb);
        $length = (int) config('platform.search.snippet_length');

        return array_map(fn (stdClass $row): PlayerHitData => new PlayerHitData(
            username: (string) $row->username,
            displayName: $row->display_name === null ? null : (string) $row->display_name,
            avatarUrl: $row->avatar_media_id === null ? null : ($avatars[(int) $row->avatar_media_id] ?? null),
            bio: $row->bio === null ? null : Str::limit((string) $row->bio, $length),
        ), $rows);
    }

    /**
     * A rank as text that casts back to the same `real`.
     */
    private static function exact(mixed $rank): string
    {
        return is_float($rank) ? sprintf('%.9g', $rank) : (string) $rank;
    }
}
