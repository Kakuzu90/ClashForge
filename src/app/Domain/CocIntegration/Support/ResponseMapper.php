<?php

namespace App\Domain\CocIntegration\Support;

use App\Domain\CocIntegration\Data\AchievementData;
use App\Domain\CocIntegration\Data\ClanData;
use App\Domain\CocIntegration\Data\ClanMemberData;
use App\Domain\CocIntegration\Data\ClanTag;
use App\Domain\CocIntegration\Data\LabelData;
use App\Domain\CocIntegration\Data\LeagueData;
use App\Domain\CocIntegration\Data\LocationData;
use App\Domain\CocIntegration\Data\PlayerClanData;
use App\Domain\CocIntegration\Data\PlayerData;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Data\UnitData;
use App\Domain\CocIntegration\Enums\TokenVerificationStatus;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use Carbon\CarbonImmutable;

/**
 * Supercell JSON → our DTOs (specs/09 §8). Nothing past this class reads an API array key.
 */
final class ResponseMapper
{
    public function player(Payload $p, ?CarbonImmutable $fetchedAt = null, bool $stale = false): PlayerData
    {
        return new PlayerData(
            tag: $this->playerTag($p),
            name: $p->string('name'),
            townHallLevel: $p->nullableInt('townHallLevel'),
            expLevel: $p->nullableInt('expLevel'),
            trophies: $p->nullableInt('trophies'),
            bestTrophies: $p->nullableInt('bestTrophies'),
            warStars: $p->nullableInt('warStars'),
            attackWins: $p->nullableInt('attackWins'),
            defenseWins: $p->nullableInt('defenseWins'),
            donations: $p->nullableInt('donations'),
            league: $this->league($p->object('league')),
            clan: $this->playerClan($p),
            labels: array_map($this->label(...), $p->objects('labels')),
            heroes: array_map($this->unit(...), $p->objects('heroes')),
            troops: array_map($this->unit(...), $p->objects('troops')),
            spells: array_map($this->unit(...), $p->objects('spells')),
            heroEquipment: array_map($this->unit(...), $p->objects('heroEquipment')),
            achievements: array_map($this->achievement(...), $p->objects('achievements')),
            rawPayload: $p->all(),
            fetchedAt: $fetchedAt,
            stale: $stale,
        );
    }

    public function clan(Payload $p, ?CarbonImmutable $fetchedAt = null, bool $stale = false): ClanData
    {
        $location = $p->object('location');

        return new ClanData(
            tag: $this->clanTag($p),
            name: $p->string('name'),
            description: $p->nullableString('description'),
            badgeUrls: $p->urls('badgeUrls'),
            level: $p->nullableInt('clanLevel'),
            points: $p->nullableInt('clanPoints'),
            memberCount: $p->nullableInt('members'),
            warFrequency: $p->nullableString('warFrequency'),
            warLeague: $this->league($p->object('warLeague')),
            capitalHallLevel: $p->object('clanCapital')?->nullableInt('capitalHallLevel'),
            requiredTownHall: $p->nullableInt('requiredTownhallLevel'),
            requiredTrophies: $p->nullableInt('requiredTrophies'),
            type: $p->nullableString('type'),
            location: $location === null ? null : new LocationData(
                id: $location->nullableInt('id'),
                name: $location->string('name'),
                countryCode: $location->nullableString('countryCode'),
            ),
            members: array_map($this->member(...), $p->objects('memberList')),
            rawPayload: $p->all(),
            fetchedAt: $fetchedAt,
            stale: $stale,
        );
    }

    /**
     * The verifytoken answer. Only `status` is read: the response echoes the token, which must go
     * no further than this request (specs/09 §9).
     */
    public function tokenStatus(Payload $p): TokenVerificationStatus
    {
        return match ($p->string('status')) {
            'ok' => TokenVerificationStatus::Ok,
            'invalid' => TokenVerificationStatus::Invalid,
            default => throw CocApiFailure::malformed('verifytoken: unknown status'),
        };
    }

    private function playerClan(Payload $p): ?PlayerClanData
    {
        $clan = $p->object('clan');

        if ($clan === null) {
            return null;
        }

        return new PlayerClanData(
            tag: $this->clanTag($clan),
            name: $clan->string('name'),
            role: $p->nullableString('role'),
            level: $clan->nullableInt('clanLevel'),
            badgeUrls: $clan->urls('badgeUrls'),
        );
    }

    private function member(Payload $m): ClanMemberData
    {
        return new ClanMemberData(
            tag: $this->playerTag($m),
            name: $m->string('name'),
            role: $m->nullableString('role'),
            townHallLevel: $m->nullableInt('townHallLevel'),
            expLevel: $m->nullableInt('expLevel'),
            trophies: $m->nullableInt('trophies'),
            league: $this->league($m->object('league')),
            donations: $m->nullableInt('donations'),
            donationsReceived: $m->nullableInt('donationsReceived'),
        );
    }

    private function league(?Payload $l): ?LeagueData
    {
        return $l === null ? null : new LeagueData(
            id: $l->nullableInt('id'),
            name: $l->string('name'),
            iconUrls: $l->urls('iconUrls'),
        );
    }

    private function label(Payload $l): LabelData
    {
        return new LabelData(
            id: $l->nullableInt('id'),
            name: $l->string('name'),
            iconUrls: $l->urls('iconUrls'),
        );
    }

    private function unit(Payload $u): UnitData
    {
        return new UnitData(
            name: $u->string('name'),
            level: $u->int('level'),
            maxLevel: $u->nullableInt('maxLevel'),
            village: $u->nullableString('village'),
            superTroopIsActive: $u->bool('superTroopIsActive'),
        );
    }

    private function achievement(Payload $a): AchievementData
    {
        return new AchievementData(
            name: $a->string('name'),
            stars: $a->nullableInt('stars'),
            value: $a->nullableInt('value'),
            target: $a->nullableInt('target'),
            village: $a->nullableString('village'),
        );
    }

    private function playerTag(Payload $p): PlayerTag
    {
        return PlayerTag::tryFrom($p->string('tag')) ?? throw CocApiFailure::malformed('a player tag is not a valid tag');
    }

    private function clanTag(Payload $p): ClanTag
    {
        return ClanTag::tryFrom($p->string('tag')) ?? throw CocApiFailure::malformed('a clan tag is not a valid tag');
    }
}
