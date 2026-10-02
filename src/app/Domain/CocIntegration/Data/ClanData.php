<?php

namespace App\Domain\CocIntegration\Data;

/**
 * `/clans/{tag}` mapped at the client boundary (specs/09 §8). The API calls the member count
 * `members` and the list `memberList`; here they are `memberCount` and `members`.
 */
final readonly class ClanData
{
    /**
     * @param  array<string, string>  $badgeUrls  size name → URL, stored and rendered unmodified
     * @param  list<ClanMemberData>  $members
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public ClanTag $tag,
        public string $name,
        public ?string $description,
        public array $badgeUrls,
        public ?int $level,
        public ?int $points,
        public ?int $memberCount,
        public ?string $warFrequency,
        public ?LeagueData $warLeague,
        public ?int $capitalHallLevel,
        public ?int $requiredTownHall,
        public ?int $requiredTrophies,
        public ?string $type,
        public ?LocationData $location,
        public array $members,
        public array $rawPayload,
    ) {}
}
