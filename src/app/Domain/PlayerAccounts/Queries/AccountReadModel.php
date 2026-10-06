<?php

namespace App\Domain\PlayerAccounts\Queries;

use App\Domain\Clans\Enums\ClanRole;
use App\Domain\Clans\Services\ClanReadModel;
use App\Domain\GameAssets\Enums\Village;
use App\Domain\GameAssets\Services\GameAssetResolver;
use App\Domain\PlayerAccounts\Data\AccountClanData;
use App\Domain\PlayerAccounts\Data\AccountDetailData;
use App\Domain\PlayerAccounts\Data\AccountStatData;
use App\Domain\PlayerAccounts\Data\OwnCocAccountData;
use App\Domain\PlayerAccounts\Data\PlayerCardData;
use App\Domain\PlayerAccounts\Data\ProgressionGroupData;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Models\CocAccountSnapshot;
use App\Domain\PlayerAccounts\Support\ProgressionGrid;
use App\Domain\PlayerAccounts\Support\RefreshCooldown;
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
     * The stats on the account page, with the column each reads (specs/18 §4, §6).
     */
    private const STATS = [
        'trophies' => 'Trophies',
        'best_trophies' => 'Best trophies',
        'war_stars' => 'War stars',
        'xp_level' => 'XP level',
        'donations' => 'Troops donated',
        'donations_received' => 'Troops received',
        'builder_trophies' => 'Builder Base trophies',
        'best_builder_trophies' => 'Best Builder Base trophies',
    ];

    /**
     * The stats snapshots also record, so only these get a delta.
     */
    private const SNAPSHOT_STATS = ['trophies', 'best_trophies', 'war_stars', 'xp_level', 'donations'];

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
            ->map(fn (CocAccount $account): OwnCocAccountData => $this->data($user, $account))
            ->all());
    }

    public function ownRow(User $user, string $ulid): ?OwnCocAccountData
    {
        $account = CocAccount::query()->where('ulid', $ulid)->where('user_id', $user->id)->first();

        return $account === null ? null : $this->data($user, $account);
    }

    private function data(User $user, CocAccount $account): OwnCocAccountData
    {
        return new OwnCocAccountData(
            ulid: $account->ulid,
            tag: $account->tag,
            name: $account->ign,
            status: $account->status,
            statusLabel: $account->status->label(),
            townHallLevel: $account->th_level,
            featured: $account->is_featured,
            canFeature: ! $account->is_featured && Gate::forUser($user)->allows('feature', $account),
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

        $builderLeague = $this->builderLeague($account);
        $canRefresh = $viewer !== null && Gate::forUser($viewer)->allows('refresh', $account);

        return new AccountDetailData(
            card: $this->card($account, $clanHidden),
            stats: array_map(fn (string $column, string $label): AccountStatData => new AccountStatData(
                key: $column,
                label: $label,
                value: $this->value($column, $account),
                delta: $this->delta($column, $account, $baseline),
            ), array_keys(self::STATS), self::STATS),
            builderLeagueName: $builderLeague['name'] ?? null,
            builderLeague: $builderLeague === null ? null : $this->assets->league($builderLeague['id'], $builderLeague['name'], null, Village::Builder),
            builderHall: $account->builder_hall_level === null ? null : $this->assets->townHall($account->builder_hall_level, Village::Builder),
            deltaDays: $days,
            notFound: $account->api_sync_failures >= (int) config('coc.sync.not_found_stale'),
            isOwn: $isOwn,
            canVerify: $viewer !== null && Gate::forUser($viewer)->allows('verify', $account),
            canDetach: $viewer !== null && Gate::forUser($viewer)->allows('detach', $account),
            canFeature: $viewer !== null && ! $account->is_featured && Gate::forUser($viewer)->allows('feature', $account),
            canRefresh: $canRefresh,
            refreshWaitSeconds: $canRefresh ? RefreshCooldown::wait($viewer->id, $account->id) : 0,
            indexable: $account->status === CocAccountStatus::Verified && $owner !== null && $this->privacy->isIndexable($owner),
            disputeUlid: $isOwn && $account->status === CocAccountStatus::Disputed
                ? CocAccountDispute::query()->active()->where('coc_account_id', $account->id)->where('current_holder_id', $viewer->id)->value('ulid')
                : null,
        );
    }

    /**
     * A stat's value; the best Builder Base trophies have no column and come from the stored payload.
     */
    private function value(string $column, CocAccount $account): ?int
    {
        if ($column === 'best_builder_trophies') {
            $best = $account->raw_payload['bestBuilderBaseTrophies'] ?? null;

            return is_int($best) ? $best : null;
        }

        return $account->{$column};
    }

    /**
     * The ranked league tier from the stored payload (the API's `leagueTier`, which replaced
     * `league` for Home Village ranked play), or null; the league columns are the fallback.
     *
     * @return array{id: ?int, name: string, icon: ?string}|null
     */
    private function leagueTier(CocAccount $account): ?array
    {
        $tier = $account->raw_payload['leagueTier'] ?? null;

        if (! is_array($tier) || ! is_string($tier['name'] ?? null) || $tier['name'] === '') {
            return null;
        }

        $icon = $tier['iconUrls']['large'] ?? $tier['iconUrls']['small'] ?? null;

        return ['id' => is_int($tier['id'] ?? null) ? $tier['id'] : null, 'name' => $tier['name'], 'icon' => is_string($icon) ? $icon : null];
    }

    /**
     * The Builder Base league from the stored payload (no column holds it), or null.
     *
     * @return array{id: ?int, name: string}|null
     */
    private function builderLeague(CocAccount $account): ?array
    {
        $league = $account->raw_payload['builderBaseLeague'] ?? null;

        if (! is_array($league) || ! is_string($league['name'] ?? null) || $league['name'] === '') {
            return null;
        }

        return ['id' => is_int($league['id'] ?? null) ? $league['id'] : null, 'name' => $league['name']];
    }

    private function delta(string $column, CocAccount $account, ?CocAccountSnapshot $baseline): ?int
    {
        if (! in_array($column, self::SNAPSHOT_STATS, true) || $account->{$column} === null || $baseline?->{$column} === null) {
            return null;
        }

        return (int) $account->{$column} - (int) $baseline->{$column};
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
        $tier = $this->leagueTier($account);
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
            leagueName: $tier['name'] ?? $account->league_name,
            league: match (true) {
                $tier !== null => $this->assets->leagueTier($tier['id'], $tier['name'], $tier['icon']),
                $account->league_name !== null => $this->assets->league($account->league_id, $account->league_name, $account->league_icon_url),
                default => null,
            },
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
