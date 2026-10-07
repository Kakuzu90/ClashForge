<?php

use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\BaseModerationState;
use App\Domain\Bases\Enums\BaseStatus;
use App\Domain\Bases\Enums\BaseVisibility;
use App\Domain\Bases\Support\BaseLink;
use App\Domain\Bases\Support\LayoutHash;
use App\Domain\Bases\Support\TagName;
use App\Domain\Bases\Support\ThLevel;
use Tests\TestCase;

// P3-01: FR-BASE-2 (base links), FR-BASE-4 (tags), FR-BASE-1 (Town Hall); the enums are the specs/07 CHECK lists.

uses(TestCase::class);

const LAYOUT_ID = 'TH16:WB:AAAAKgAAAAJ0nZ-ZqxY1';

it('keeps one canonical form of a game link, with only its action and id', function (string $typed) {
    $link = BaseLink::from($typed);

    expect($link->value)->toBe('https://link.clashofclans.com/en?action=OpenLayout&id=TH16%3AWB%3AAAAAKgAAAAJ0nZ-ZqxY1')
        ->and($link->layoutId())->toBe(LAYOUT_ID);
})->with([
    'as shared' => ['https://link.clashofclans.com/en?action=OpenLayout&id=TH16%3AWB%3AAAAAKgAAAAJ0nZ-ZqxY1'],
    'spaces around' => ['  https://link.clashofclans.com/en?action=OpenLayout&id=TH16%3AWB%3AAAAAKgAAAAJ0nZ-ZqxY1 '],
    'no language' => ['https://link.clashofclans.com/?action=OpenLayout&id=TH16%3AWB%3AAAAAKgAAAAJ0nZ-ZqxY1'],
    'host in capitals' => ['https://LINK.clashofclans.com/en?action=OpenLayout&id=TH16%3AWB%3AAAAAKgAAAAJ0nZ-ZqxY1'],
    'tracking parameters' => ['https://link.clashofclans.com/en?utm_source=x&action=OpenLayout&id=TH16%3AWB%3AAAAAKgAAAAJ0nZ-ZqxY1&ref=y'],
    'unencoded id' => ['https://link.clashofclans.com/en?action=OpenLayout&id=TH16:WB:AAAAKgAAAAJ0nZ-ZqxY1'],
]);

it('keeps the language of the link', function () {
    expect(BaseLink::from('https://link.clashofclans.com/de?action=OpenLayout&id='.LAYOUT_ID)->value)->toStartWith('https://link.clashofclans.com/de?');
});

it('refuses anything but a game layout link', function (string $typed, string $error) {
    expect(BaseLink::errorFor($typed))->toBe($error);
})->with([
    'http' => ['http://link.clashofclans.com/en?action=OpenLayout&id='.LAYOUT_ID, 'Paste the link from the game: it starts with https://link.clashofclans.com/.'],
    'another host' => ['https://link.clashofclans.com.evil.test/en?action=OpenLayout&id='.LAYOUT_ID, 'Paste the link from the game: it starts with https://link.clashofclans.com/.'],
    'not a link' => ['TH16 war base', 'Paste the link from the game: it starts with https://link.clashofclans.com/.'],
    'a player link' => ['https://link.clashofclans.com/en?action=OpenPlayerProfile&tag=2PQ8GRJC', 'This link does not open a base layout. Copy the link of a base in the game.'],
    'no id' => ['https://link.clashofclans.com/en?action=OpenLayout', 'This base link is incomplete. Copy it again from the game.'],
    'id with script' => ['https://link.clashofclans.com/en?action=OpenLayout&id=%3Cscript%3E', 'This base link is incomplete. Copy it again from the game.'],
    'id too short' => ['https://link.clashofclans.com/en?action=OpenLayout&id=TH16', 'This base link is incomplete. Copy it again from the game.'],
]);

it('hashes the layout id, so two links to one layout match', function () {
    $a = LayoutHash::of(BaseLink::from('https://link.clashofclans.com/en?action=OpenLayout&id='.LAYOUT_ID));
    $b = LayoutHash::of(BaseLink::from('https://link.clashofclans.com/fr?ref=1&action=OpenLayout&id='.rawurlencode(LAYOUT_ID)));

    expect($a->value)->toBe(hash('sha256', LAYOUT_ID))->and($a->equals($b))->toBeTrue()
        ->and(LayoutHash::errorFor('xyz'))->not->toBeNull();
});

it('normalises tags to lowercase-kebab and caps their length', function () {
    expect(TagName::from('  Anti Air ')->value)->toBe('anti-air')
        ->and(TagName::from('ANTI_ROOT__rider!')->value)->toBe('anti-root-rider')
        ->and(TagName::from('--ring--base--')->value)->toBe('ring-base')
        ->and(TagName::errorFor('!!!'))->toBe('A tag needs at least one letter or number.')
        ->and(TagName::errorFor(str_repeat('a', (int) config('bases.tag_max_length') + 1)))->toBe('A tag has at most '.config('bases.tag_max_length').' characters.')
        ->and(TagName::errorFor(str_repeat('a', (int) config('bases.tag_max_length'))))->toBeNull();
});

it('accepts the configured Town Halls only', function () {
    $min = (int) config('bases.th_min');
    $max = (int) config('bases.th_max');

    expect(ThLevel::errorFor($min))->toBeNull()->and(ThLevel::errorFor($max))->toBeNull()
        ->and(ThLevel::errorFor($min - 1))->toBe("Choose a Town Hall from {$min} to {$max}.")
        ->and(ThLevel::errorFor($max + 1))->not->toBeNull()
        ->and(fn () => new ThLevel($max + 1))->toThrow(InvalidArgumentException::class);
});

it('matches the specs/07 value lists', function (string $enum, array $values) {
    expect($enum::values())->toBe($values);
})->with([
    [BaseCategory::class, ['war', 'cwl', 'farming', 'trophy', 'legend', 'anti_3_star', 'anti_2_star', 'hybrid', 'progress', 'troll']],
    [BaseStatus::class, ['draft', 'processing', 'published', 'hidden', 'removed']],
    [BaseVisibility::class, ['public', 'unlisted', 'private']],
    [BaseModerationState::class, ['clean', 'flagged', 'under_review', 'actioned']],
]);

it('labels the categories as FR-BASE-3 names them', function () {
    expect(array_map(fn (BaseCategory $c) => $c->label(), BaseCategory::cases()))
        ->toBe(['War', 'CWL', 'Farming', 'Trophy', 'Legend League', 'Anti-3-Star', 'Anti-2-Star', 'Hybrid', 'Progress Base', 'Funny/Troll']);
});
