<?php

namespace App\Domain\CocIntegration\Data;

final readonly class ClanMemberData
{
    public function __construct(
        public PlayerTag $tag,
        public string $name,
        public ?string $role,
        public ?int $townHallLevel,
        public ?int $expLevel,
        public ?int $trophies,
        public ?LeagueData $league,
        public ?int $donations,
        public ?int $donationsReceived,
    ) {}
}
