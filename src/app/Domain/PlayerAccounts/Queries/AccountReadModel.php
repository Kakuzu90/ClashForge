<?php

namespace App\Domain\PlayerAccounts\Queries;

use App\Domain\Clans\Enums\ClanRole;
use App\Domain\Clans\Services\ClanReadModel;
use App\Domain\GameAssets\Services\GameAssetResolver;
use App\Domain\PlayerAccounts\Data\AccountClanData;
use App\Domain\PlayerAccounts\Data\AccountDetailData;
use App\Domain\PlayerAccounts\Data\AccountStatData;
use App\Domain\PlayerAccounts\Data\OwnCocAccountData;
use App\Domain\PlayerAccounts\Data\PlayerCardData;
use App\Domain\PlayerAccounts\Data\ProgressionGroupData;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountSnapshot;
use App\Domain\PlayerAccounts\Support\ProgressionGrid;
use App\Domain\Users\Services\PrivacyPolicyResolver;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;

/**
 * Reads of CoC accounts for pages. The owner's own lists are scoped to the owner, so another
 * user's ulid finds nothing; the account page asks `CocAccountPolicy::view` and answers an unknown
 * and a hidden account alike (specs/04 §3, 404 first).
 */
class AccountReadModel
{
    /**
     * The stats on the account page, with the column each reads (specs/18 §4).
     */
    private const STATS = [
        'trophies' => 'Trophies',
        'best_trophies' => 'Best trophies',
        'war_stars' => 'War stars',
        'xp_level' => 'XP level',
    ];

    public function __construct(
        private readonly PrivacyPolicyResolver $privacy,
        private readonly ClanReadModel $clans,
        private readonly GameAssetResolver $assets,
        private readonly ProgressionGrid $grid,
    ) {}

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

    /**
     * The account page without its grids, or null when this viewer may not see it.
     */
    public function detail(?User $viewer, string $ulid): ?AccountDetailData
    {
        $account = $this->visible($viewer, $ulid);

        if ($account === null) {
            return null;
        }

        $isOwn = $viewer !== null && $viewer->id === $account->user_id;
        $owner = $account->user;
        $clanHidden = ! $isOwn && $owner !== null && ! $this->privacy->settingsFor($owner->id)->showClan;
        $days = (int) config('coc.display.delta_days');
        $baseline = CocAccountSnapshot::query()
            ->where('coc_account_id', $account->id)
            ->where('captured_at', '<=', Date::now()->subDays($days))
            ->orderByDesc('captured_at')
            ->first();

        return new AccountDetailData(
            card: $this->card($account, $clanHidden),
            stats: array_map(fn (string $column, string $label): AccountStatData => new AccountStatData(
                key: $column,
                label: $label,
                value: $account->{$column},
                delta: $account->{$column} === null || $baseline?->{$column} === null ? null : $account->{$column} - $baseline->{$column},
            ), array_keys(self::STATS), self::STATS),
            deltaDays: $days,
            notFound: $account->api_sync_failures >= (int) config('coc.sync.not_found_stale'),
            isOwn: $isOwn,
            canVerify: $viewer !== null && Gate::forUser($viewer)->allows('verify', $account),
            indexable: $account->status === CocAccountStatus::Verified && $owner !== null && $this->privacy->isIndexable($owner),
        );
    }

    /**
     * The progression grids (a deferred prop), under the same rule as the page.
     *
     * @return list<ProgressionGroupData>
     */
    public function progression(?User $viewer, string $ulid): array
    {
        $account = $this->visible($viewer, $ulid);

        return $account === null ? [] : $this->grid->groups($account->heroes, $account->hero_equipment, $account->troops, $account->spells);
    }

    private function visible(?User $viewer, string $ulid): ?CocAccount
    {
        $account = CocAccount::query()->with('user')->where('ulid', $ulid)->first();

        return $account !== null && Gate::forUser($viewer)->allows('view', $account) ? $account : null;
    }

    private function card(CocAccount $account, bool $clanHidden): PlayerCardData
    {
        $synced = $account->api_synced_at;
        $staleAfter = Date::now()->subHours((int) config('coc.display.stale_hours'));

        return new PlayerCardData(
            ulid: $account->ulid,
            tag: $account->tag,
            name: $account->ign,
            status: $account->status,
            statusLabel: $account->status->label(),
            townHallLevel: $account->th_level,
            townHall: $account->th_level === null ? null : $this->assets->townHall($account->th_level),
            builderHallLevel: $account->builder_hall_level,
            xpLevel: $account->xp_level,
            trophies: $account->trophies,
            warStars: $account->war_stars,
            leagueName: $account->league_name,
            league: $account->league_name === null ? null : $this->assets->league($account->league_id, $account->league_name, $account->league_icon_url),
            clan: $clanHidden ? null : $this->clan($account),
            clanHidden: $clanHidden,
            featured: $account->is_featured,
            stale: $account->api_sync_failures >= (int) config('coc.sync.not_found_stale') || $synced === null || $synced->lessThan($staleAfter),
            syncedAt: $synced,
            syncedAgeSeconds: $synced === null ? null : max(0, (int) $synced->diffInSeconds(Date::now(), true)),
        );
    }

    private function clan(CocAccount $account): ?AccountClanData
    {
        if ($account->clan_id === null) {
            return null;
        }

        $clan = $this->clans->summaries([$account->clan_id])[$account->clan_id] ?? null;

        return $clan === null ? null : new AccountClanData(
            tag: $clan->tag,
            name: $clan->name,
            level: $clan->level,
            roleLabel: $account->clan_role === null ? null : ClanRole::tryFrom($account->clan_role)?->label(),
            badge: $this->assets->clanBadge($clan->badgeUrls, $clan->name, 'small'),
        );
    }
}
