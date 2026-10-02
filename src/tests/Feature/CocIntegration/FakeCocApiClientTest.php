<?php

use App\Domain\CocIntegration\Contracts\CocApiClient;
use App\Domain\CocIntegration\Data\ClanTag;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\CocLookupStatus;
use App\Domain\CocIntegration\Enums\TokenVerificationStatus;
use App\Domain\CocIntegration\Services\ClanLookup;
use App\Domain\CocIntegration\Services\PlayerLookup;
use App\Domain\CocIntegration\Services\TokenVerifier;
use App\Domain\CocIntegration\Support\FakeCocApiClient;
use Illuminate\Support\Facades\Http;
use Tests\Support\Coc\InteractsWithCoc;

// The fake is the default outside production (specs/05 §6) and serves the fixtures.

uses(InteractsWithCoc::class);

it('is the client in tests and local development', function () {
    expect(config('coc.driver'))->toBe('fake')
        ->and(app(CocApiClient::class))->toBe($this->fakeCoc())
        ->and(app(CocApiClient::class))->toBeInstanceOf(FakeCocApiClient::class);
});

it('serves fixture players and clans without any HTTP', function () {
    Http::fake();

    $player = app(PlayerLookup::class)->find(PlayerTag::from('#2pq8grjc'));
    $clan = app(ClanLookup::class)->find(ClanTag::from('#2Q8URJ9L'));

    expect($player->status)->toBe(CocLookupStatus::Found)
        ->and($player->player?->name)->toBe('Fixture Chief')
        ->and($clan->clan?->name)->toBe('Fixture Clan')
        ->and($this->fakeCoc()->calls())->toBe([
            ['endpoint' => 'players', 'tag' => '#2PQ8GRJC'],
            ['endpoint' => 'clans', 'tag' => '#2Q8URJ9L'],
        ]);
    Http::assertNothingSent();
});

it('answers not found for a tag with no fixture', function () {
    expect(app(PlayerLookup::class)->find(PlayerTag::from('#PYLQ'))->status)->toBe(CocLookupStatus::NotFound);
});

it('serves a scripted player over the fixtures', function () {
    $this->fakeCoc()->withPlayer(['tag' => '#2PQ8GRJC', 'name' => 'Scripted', 'townHallLevel' => 11]);

    expect(app(PlayerLookup::class)->find(PlayerTag::from('#2PQ8GRJC'))->player?->name)->toBe('Scripted');
});

it('fails the next call as scripted', function () {
    $this->fakeCoc()->failNext(CocFailureReason::Throttled, 12)->notFoundNext();

    $first = app(PlayerLookup::class)->find(PlayerTag::from('#2PQ8GRJC'));
    $second = app(PlayerLookup::class)->find(PlayerTag::from('#2PQ8GRJC'));
    $third = app(PlayerLookup::class)->find(PlayerTag::from('#2PQ8GRJC'));

    expect($first->status)->toBe(CocLookupStatus::Unavailable)
        ->and($first->failure)->toBe(CocFailureReason::Throttled)
        ->and($first->retryAfter)->toBe(12)
        ->and($second->status)->toBe(CocLookupStatus::NotFound)
        ->and($third->status)->toBe(CocLookupStatus::Found);
});

it('accepts the configured local token and scripted tokens only', function () {
    $tag = PlayerTag::from('#2PQ8GRJC');
    $this->fakeCoc()->acceptToken($tag, 'scripted-token');

    expect(app(TokenVerifier::class)->verify($tag, (string) config('coc.fake.valid_token'))->status)->toBe(TokenVerificationStatus::Ok)
        ->and(app(TokenVerifier::class)->verify($tag, 'scripted-token')->status)->toBe(TokenVerificationStatus::Ok)
        ->and(app(TokenVerifier::class)->verify($tag, 'wrong-token')->status)->toBe(TokenVerificationStatus::Invalid)
        ->and(app(TokenVerifier::class)->verify(PlayerTag::from('#PYLQ'), 'scripted-token')->status)->toBe(TokenVerificationStatus::NotFound);
});

it('records calls by tag, never by token', function () {
    app(TokenVerifier::class)->verify(PlayerTag::from('#2PQ8GRJC'), 'secret-in-game-token');

    expect(json_encode($this->fakeCoc()->calls()))->not->toContain('secret-in-game-token');
});
