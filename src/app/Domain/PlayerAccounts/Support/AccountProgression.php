<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\PlayerAccounts\Enums\SnapshotSource;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountSnapshot;
use Illuminate\Support\Facades\Date;

/**
 * Which `coc_accounts` values count as progression, and so write a snapshot when they change
 * (specs/09 §6, owner decision 2026-10-02). Trophies, attack and defense wins and donations move on
 * almost every sync: each snapshot stores them, but they never trigger one.
 */
final class AccountProgression
{
    public const SCALARS = ['th_level', 'builder_hall_level', 'xp_level', 'best_trophies', 'war_stars', 'clan_tag', 'league_id'];

    public const UNIT_LISTS = ['troops', 'heroes', 'spells', 'hero_equipment'];

    /**
     * Whether `$next` (`AccountGameData::attributes()`) differs from the stored row in progression.
     *
     * @param  array<string, mixed>  $next
     */
    public static function changed(CocAccount $account, array $next): bool
    {
        foreach (self::SCALARS as $column) {
            if (self::scalar($account->getAttribute($column)) !== self::scalar($next[$column] ?? null)) {
                return true;
            }
        }

        foreach (self::UNIT_LISTS as $column) {
            if (self::levels((array) $account->getAttribute($column)) !== self::levels((array) ($next[$column] ?? []))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whole seconds, and at most one per account per second: two verifications of one row in the
     * same second keep the first instead of failing on `(coc_account_id, captured_at)`.
     */
    public static function snapshot(CocAccount $account, SnapshotSource $source): CocAccountSnapshot
    {
        return $account->snapshots()->firstOrCreate(['captured_at' => Date::now()->startOfSecond()], [
            ...$account->only([...self::SCALARS, 'trophies', 'attack_wins', 'defense_wins', 'donations', ...self::UNIT_LISTS]),
            'source' => $source,
        ]);
    }

    /**
     * Drivers may hand integers back as strings; a level is a level either way.
     */
    private static function scalar(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^-?\d+$/D', $value) ? (int) $value : $value;
    }

    /**
     * Unit levels keyed by village and name, so a reordered list is no change.
     *
     * @param  array<mixed>  $units
     * @return array<string, mixed>
     */
    private static function levels(array $units): array
    {
        $levels = [];
        foreach ($units as $unit) {
            if (is_array($unit)) {
                $levels[($unit['village'] ?? '').'|'.($unit['name'] ?? '')] = $unit['level'] ?? null;
            }
        }
        ksort($levels);

        return $levels;
    }
}
