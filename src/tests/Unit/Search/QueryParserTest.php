<?php

use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Search\Services\QueryParser;
use Tests\TestCase;

// specs/17 §3: the tag short-circuit and the structured query parser (P3-05).

uses(TestCase::class);

it('reads a Town Hall in any of its spellings', function (string $text, int $level, string $match) {
    $parsed = (new QueryParser)->parse($text);

    expect($parsed->thLevel)->toBe($level)
        ->and($parsed->filters[0]->key)->toBe('th')
        ->and($parsed->filters[0]->label)->toBe("Town Hall {$level}")
        ->and($parsed->filters[0]->match)->toBe($match);
})->with([
    ['TH17 ring', 17, 'TH17'],
    ['th 16 ring', 16, 'th 16'],
    ['Town Hall 15 ring', 15, 'Town Hall 15'],
    ['ring th-14', 14, 'th-14'],
]);

it('leaves a level outside the allowed range, and words that only contain "th", as text', function () {
    $parser = new QueryParser;

    expect($parser->parse('th99 ring')->thLevel)->toBeNull()
        ->and($parser->parse('th99 ring')->term)->toBe('th99 ring')
        ->and($parser->parse('teeth17')->thLevel)->toBeNull();
});

it('reads a category by name or synonym, the longest phrase first', function (string $text, BaseCategory $category, string $term) {
    $parsed = (new QueryParser)->parse($text);

    expect($parsed->category)->toBe($category)->and($parsed->term)->toBe($term);
})->with([
    ['TH17 Anti-3-Star', BaseCategory::Anti3Star, ''],
    ['anti 2 star box', BaseCategory::Anti2Star, 'box'],
    ['clan war league ring', BaseCategory::Cwl, 'ring'],
    ['legend league island', BaseCategory::Legend, 'island'],
    ['farm base', BaseCategory::Farming, ''],
    ['Funny layout', BaseCategory::Troll, ''],
]);

it('drops words that only say "base" once a filter was read, and keeps them otherwise', function () {
    $parser = new QueryParser;

    expect($parser->parse('TH16 war base layout')->term)->toBe('')
        ->and($parser->parse('best base ever')->term)->toBe('best base ever')
        ->and($parser->parse('best base ever')->filters)->toBe([]);
});

it('treats a player tag as a tag, and lowercase words without # as text', function () {
    $parser = new QueryParser;

    expect($parser->parse('#2pp0')->tag?->value)->toBe('#2PP0')
        ->and($parser->parse('#2PPQ')->tag?->value)->toBe('#2PPQ')
        ->and($parser->parse('2PPQ')->tag?->value)->toBe('#2PPQ')
        ->and($parser->parse('pug')->tag)->toBeNull()
        ->and($parser->parse('pug')->term)->toBe('pug')
        ->and($parser->parse('#hello')->tag)->toBeNull()
        ->and($parser->parse('#hello')->term)->toBe('#hello');
});

it('collapses spaces and keeps the rest of the text in order', function () {
    expect((new QueryParser)->parse("  ring   TH15\tfor   pushing ")->term)->toBe('ring for');
});
