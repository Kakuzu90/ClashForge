<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\CocIntegration\Data\AchievementData;
use App\Domain\CocIntegration\Data\LabelData;
use App\Domain\CocIntegration\Data\PlayerData;
use App\Domain\CocIntegration\Data\UnitData;

/**
 * `PlayerData` → the game columns of `coc_accounts` (FR-COC-3, specs/07). Unit lists keep every
 * unit, known or not, in our own key names (specs/09 §8). The tag is not among them: it is
 * identity, set with forceFill where the row is created.
 */
final class AccountGameData
{
    /**
     * @return array<string, mixed>
     */
    public static function attributes(PlayerData $player): array
    {
        return [
            'ign' => mb_substr($player->name, 0, 30),
            'th_level' => $player->townHallLevel,
            'builder_hall_level' => $player->builderHallLevel,
            'xp_level' => $player->expLevel,
            'trophies' => $player->trophies,
            'best_trophies' => $player->bestTrophies,
            'builder_trophies' => $player->builderBaseTrophies,
            'war_stars' => $player->warStars,
            'attack_wins' => $player->attackWins,
            'defense_wins' => $player->defenseWins,
            'donations' => $player->donations,
            'donations_received' => $player->donationsReceived,
            'clan_tag' => $player->clan?->tag->value,
            'clan_role' => $player->clan?->role,
            'league_id' => $player->league?->id,
            'league_name' => $player->league === null ? null : mb_substr($player->league->name, 0, 50),
            'league_icon_url' => $player->league === null ? null
                : ($player->league->iconUrls['medium'] ?? $player->league->iconUrls['small'] ?? $player->league->iconUrls['tiny'] ?? null),
            'troops' => self::units($player->troops),
            'heroes' => self::units($player->heroes),
            'spells' => self::units($player->spells),
            'hero_equipment' => self::units($player->heroEquipment),
            'achievements' => array_map(fn (AchievementData $a): array => [
                'name' => $a->name, 'stars' => $a->stars, 'value' => $a->value, 'target' => $a->target, 'village' => $a->village,
            ], $player->achievements),
            'labels' => array_map(fn (LabelData $l): array => ['id' => $l->id, 'name' => $l->name], $player->labels),
            'raw_payload' => $player->rawPayload,
            'api_synced_at' => $player->fetchedAt,
        ];
    }

    /**
     * @param  list<UnitData>  $units
     * @return list<array<string, mixed>>
     */
    private static function units(array $units): array
    {
        return array_map(fn (UnitData $u): array => [
            'name' => $u->name,
            'level' => $u->level,
            'max_level' => $u->maxLevel,
            'village' => $u->village,
            'super_troop_active' => $u->superTroopIsActive,
        ], $units);
    }
}
