<?php

use App\Domain\Users\Support\CountryCode;
use App\Domain\Users\Support\LanguageCodes;
use App\Domain\Users\Support\SocialLinks;
use App\Domain\Users\Support\Timezone;
use Tests\TestCase;

// specs/07 `profiles`: every stored field passes its value object first.

uses(TestCase::class);

it('accepts ISO countries and rejects regions that are not countries', function () {
    expect(CountryCode::from('de')->value)->toBe('DE')
        ->and(CountryCode::from('PH')->name())->toBe('Philippines')
        ->and(CountryCode::tryFrom('EU'))->toBeNull()
        ->and(CountryCode::tryFrom('XK'))->toBeNull()
        ->and(CountryCode::tryFrom('ZZ'))->toBeNull()
        ->and(CountryCode::tryFrom('DEU'))->toBeNull();
});

it('accepts PHP timezone identifiers only', function () {
    expect(Timezone::from('Europe/Berlin')->value)->toBe('Europe/Berlin')
        ->and(Timezone::tryFrom('Mars/Olympus'))->toBeNull()
        ->and(Timezone::tryFrom('UTC+2'))->toBeNull();
});

it('keeps up to the configured number of distinct languages in order', function () {
    expect(LanguageCodes::from(['EN', 'de', 'en'])->codes)->toBe(['en', 'de'])
        ->and(LanguageCodes::errorFor(['en', 'de', 'fr', 'es']))->toBe('Choose up to '.config('platform.profile.languages_max').' languages.')
        ->and(LanguageCodes::errorFor(['english']))->not->toBeNull()
        ->and(LanguageCodes::errorFor([['en']]))->not->toBeNull();
});

it('stores handles and builds links from fixed hosts', function () {
    $links = SocialLinks::from(['youtube' => 'clashchief', 'x' => '@chief_x', 'twitch' => 'chief_live', 'discord' => 'Chief.99', 'myspace' => 'nope']);

    expect($links->handles)->toBe(['youtube' => '@clashchief', 'twitch' => 'chief_live', 'x' => 'chief_x', 'discord' => 'chief.99'])
        ->and($links->links())->toBe([
            'youtube' => ['handle' => '@clashchief', 'url' => 'https://www.youtube.com/@clashchief'],
            'twitch' => ['handle' => 'chief_live', 'url' => 'https://www.twitch.tv/chief_live'],
            'x' => ['handle' => 'chief_x', 'url' => 'https://x.com/chief_x'],
            'discord' => ['handle' => 'chief.99', 'url' => null],
        ]);
});

it('drops empty handles', function () {
    expect(SocialLinks::from(['youtube' => '', 'x' => '   ', 'twitch' => null])->handles)->toBe([]);
});

it('rejects URLs and script schemes in every network', function (string $network, string $value) {
    expect(SocialLinks::errorFor($network, $value))->not->toBeNull();
})->with([
    ['youtube', 'https://www.youtube.com/@clashchief'],
    ['youtube', 'javascript:alert(1)'],
    ['twitch', 'https://twitch.tv/chief'],
    ['x', 'javascript:alert(1)'],
    ['x', 'x.com/chief'],
    ['discord', '<script>'],
    ['twitch', 'a b'],
]);
