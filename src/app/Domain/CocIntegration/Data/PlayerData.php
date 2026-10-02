<?php

namespace App\Domain\CocIntegration\Data;

use Carbon\CarbonImmutable;

/**
 * `/players/{tag}` mapped at the client boundary (specs/09 §8). Only the tag and name are
 * required; a field the API stops sending reads as null (specs/23 §5). `rawPayload` is the full
 * response, kept for `raw_payload` so new fields can be backfilled without a re-sync.
 * `fetchedAt` is when the API answered; `stale` means the API is unavailable and this is the last
 * good answer, served so pages can say "data from X ago" (specs/09 §5).
 */
final readonly class PlayerData
{
    /**
     * @param  list<LabelData>  $labels
     * @param  list<UnitData>  $heroes
     * @param  list<UnitData>  $troops
     * @param  list<UnitData>  $spells
     * @param  list<UnitData>  $heroEquipment
     * @param  list<AchievementData>  $achievements
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public PlayerTag $tag,
        public string $name,
        public ?int $townHallLevel,
        public ?int $expLevel,
        public ?int $trophies,
        public ?int $bestTrophies,
        public ?int $warStars,
        public ?int $attackWins,
        public ?int $defenseWins,
        public ?int $donations,
        public ?int $donationsReceived,
        public ?int $builderHallLevel,
        public ?int $builderBaseTrophies,
        public ?LeagueData $league,
        public ?PlayerClanData $clan,
        public array $labels,
        public array $heroes,
        public array $troops,
        public array $spells,
        public array $heroEquipment,
        public array $achievements,
        public array $rawPayload,
        public ?CarbonImmutable $fetchedAt = null,
        public bool $stale = false,
    ) {}
}
