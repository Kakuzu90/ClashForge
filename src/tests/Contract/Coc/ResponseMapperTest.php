<?php

use App\Domain\CocIntegration\Data\ClanData;
use App\Domain\CocIntegration\Data\PlayerData;
use App\Domain\CocIntegration\Data\UnitData;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\TokenVerificationStatus;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use App\Domain\CocIntegration\Support\Payload;
use App\Domain\CocIntegration\Support\ResponseMapper;
use Tests\Support\Coc\InteractsWithCoc;

// specs/09 §10 contract layer: the mapper against every fixture. The fixtures are synthetic until
// a developer key exists; recorded ones replace them under the same names and must pass as well.

uses(InteractsWithCoc::class);

beforeEach(function () {
    $this->mapper = new ResponseMapper;
});

function mapPlayer(object $test, string $file): PlayerData
{
    return $test->mapper->player(Payload::decode($test->cocFixtureBody($file), 'players'));
}

it('maps every player fixture', function (string $file) {
    $player = mapPlayer($this, 'players/'.$file);

    expect($player->tag->bare())->toBe(basename($file, '.json'))
        ->and($player->rawPayload)->toBe($this->cocFixture('players/'.$file));
})->with(array_map('basename', glob(dirname(__DIR__, 2).'/Fixtures/coc/players/*.json') ?: []));

it('maps every clan fixture', function (string $file) {
    $clan = $this->mapper->clan(Payload::decode($this->cocFixtureBody('clans/'.$file), 'clans'));

    expect($clan->tag->bare())->toBe(basename($file, '.json'))
        ->and($clan)->toBeInstanceOf(ClanData::class);
})->with(array_map('basename', glob(dirname(__DIR__, 2).'/Fixtures/coc/clans/*.json') ?: []));

it('maps a full player (FR-COC-3)', function () {
    $player = mapPlayer($this, 'players/2PQ8GRJC.json');

    expect($player->name)->toBe('Fixture Chief')
        ->and($player->townHallLevel)->toBe(16)
        ->and($player->expLevel)->toBe(231)
        ->and($player->trophies)->toBe(5124)
        ->and($player->bestTrophies)->toBe(5602)
        ->and($player->warStars)->toBe(1480)
        ->and($player->attackWins)->toBe(84)
        ->and($player->defenseWins)->toBe(6)
        ->and($player->donations)->toBe(2210)
        ->and($player->donationsReceived)->toBe(1650)
        ->and($player->builderHallLevel)->toBe(10)
        ->and($player->builderBaseTrophies)->toBe(4210)
        ->and($player->league?->name)->toBe('Legend League')
        ->and($player->league?->iconUrls)->toHaveKeys(['small', 'tiny', 'medium'])
        ->and($player->clan?->tag->value)->toBe('#2Q8URJ9L')
        ->and($player->clan?->role)->toBe('coLeader')
        ->and($player->clan?->level)->toBe(22)
        ->and($player->clan?->badgeUrls)->toHaveKeys(['small', 'medium', 'large'])
        ->and($player->labels[0]->name)->toBe('Clan Wars')
        ->and($player->heroes)->toHaveCount(2)
        ->and($player->troops)->toHaveCount(4)
        ->and($player->spells)->toHaveCount(2)
        ->and($player->heroEquipment)->toHaveCount(2)
        ->and($player->achievements[1]->value)->toBe(5602);

    $superTroop = collect($player->troops)->firstWhere('name', 'Super Barbarian');
    expect($superTroop)->toEqual(new UnitData('Super Barbarian', 11, 12, 'home', true))
        ->and($player->troops[0]->superTroopIsActive)->toBeFalse()
        ->and($player->troops[3]->village)->toBe('builderBase');
});

it('maps a player without a clan or league', function () {
    $player = mapPlayer($this, 'players/LQ2RJ9P0.json');

    expect($player->clan)->toBeNull()
        ->and($player->league)->toBeNull()
        ->and($player->heroEquipment)->toBe([]);
});

it('keeps units, fields and Town Hall levels it does not know yet (specs/09 §8, specs/23 §5)', function () {
    $player = mapPlayer($this, 'players/YC8V2QG9.json');

    expect($player->townHallLevel)->toBe(18)
        ->and(array_column($player->troops, 'name'))->toContain('Troop From A Future Update')
        ->and($player->heroes[0]->name)->toBe('Hero From A Future Update')
        ->and($player->rawPayload)->toHaveKey('fieldAddedNextUpdate');
});

it('reads a field the API stopped sending as null (specs/23 §5)', function () {
    $player = mapPlayer($this, 'players/GRJ0P8UV.json');

    expect($player->bestTrophies)->toBeNull()
        ->and($player->warStars)->toBeNull()
        ->and($player->donations)->toBeNull()
        ->and($player->troops)->toBe([])
        ->and($player->achievements)->toBe([]);
});

it('maps a clan', function () {
    $clan = $this->mapper->clan(Payload::decode($this->cocFixtureBody('clans/2Q8URJ9L.json'), 'clans'));

    expect($clan->name)->toBe('Fixture Clan')
        ->and($clan->level)->toBe(22)
        ->and($clan->points)->toBe(48210)
        ->and($clan->memberCount)->toBe(2)
        ->and($clan->members)->toHaveCount(2)
        ->and($clan->members[0]->role)->toBe('coLeader')
        ->and($clan->members[1]->league)->toBeNull()
        ->and($clan->warFrequency)->toBe('always')
        ->and($clan->warLeague?->name)->toBe('Champion League I')
        ->and($clan->capitalHallLevel)->toBe(10)
        ->and($clan->requiredTownHall)->toBe(13)
        ->and($clan->requiredTrophies)->toBe(3000)
        ->and($clan->type)->toBe('inviteOnly')
        ->and($clan->location?->name)->toBe('International')
        ->and($clan->badgeUrls)->toHaveKeys(['small', 'medium', 'large']);
});

it('reads the verifytoken status', function (string $file, TokenVerificationStatus $status) {
    expect($this->mapper->tokenStatus(Payload::decode($this->cocFixtureBody('responses/'.$file), 'players.verifytoken')))->toBe($status);
})->with([
    ['verifytoken-ok.json', TokenVerificationStatus::Ok],
    ['verifytoken-invalid.json', TokenVerificationStatus::Invalid],
]);

it('refuses a response whose shape changed (specs/23 §5)', function (Closure $map) {
    try {
        $map($this);
        $this->fail('Expected a malformed failure.');
    } catch (CocApiFailure $e) {
        expect($e->reason)->toBe(CocFailureReason::Malformed);
    }
})->with([
    'wrong type' => [fn ($t) => mapPlayer($t, 'responses/player-wrong-type.json')],
    'no name' => [fn ($t) => mapPlayer($t, 'responses/player-no-name.json')],
    'list body' => [fn ($t) => Payload::decode('[1,2]', 'players')],
    'empty body' => [fn ($t) => Payload::decode('', 'players')],
    'not JSON' => [fn ($t) => Payload::decode('<html>maintenance</html>', 'players')],
    'unit without a level' => [fn ($t) => $t->mapper->player(new Payload(['tag' => '#2PQ', 'name' => 'x', 'troops' => [['name' => 'Barbarian']]], 'players'))],
    'troops not a list' => [fn ($t) => $t->mapper->player(new Payload(['tag' => '#2PQ', 'name' => 'x', 'troops' => ['a' => 1]], 'players'))],
    'invalid tag' => [fn ($t) => $t->mapper->player(new Payload(['tag' => '#ABCD', 'name' => 'x'], 'players'))],
    'badge URL not a string' => [fn ($t) => $t->mapper->clan(new Payload(['tag' => '#2PQ', 'name' => 'x', 'badgeUrls' => ['small' => 5]], 'clans'))],
    'unknown token status' => [fn ($t) => $t->mapper->tokenStatus(new Payload(['status' => 'maybe'], 'players.verifytoken'))],
]);
