<?php

use App\Domain\Auth\Services\DisposableEmailDomains;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

// FR-AUTH-11 and the owner decision of 2026-10-01: the committed CC0 list, refreshed monthly
// into storage, the refreshed copy winning.

beforeEach(function () {
    $this->refreshed = storage_path('framework/testing/disposable-'.uniqid().'.txt');
    config(['platform.auth.disposable_domains_refreshed' => $this->refreshed]);
});

afterEach(fn () => File::delete($this->refreshed));

it('matches a listed domain and its subdomains, ignoring case, and nothing else', function () {
    $domains = new DisposableEmailDomains;

    expect($domains->isDisposable('someone@mailinator.com'))->toBeTrue()
        ->and($domains->isDisposable('someone@MX.Mailinator.COM'))->toBeTrue()
        ->and($domains->isDisposable('someone@gmail.com'))->toBeFalse()
        ->and($domains->isDisposable('someone@nomailinator-here.org'))->toBeFalse()
        ->and($domains->isDisposable('no-at-sign'))->toBeFalse()
        ->and($domains->isDisposable('someone@com'))->toBeFalse();
});

it('parses a list, skipping comments, blanks and junk', function () {
    expect(DisposableEmailDomains::parse("# comment\nMailinator.com\n\n  trashmail.de  \nnot a domain\nlocalhost\n"))
        ->toBe(['mailinator.com' => true, 'trashmail.de' => true]);
});

it('ships a committed list of several thousand domains', function () {
    $contents = File::get((string) config('platform.auth.disposable_domains_file'));

    expect(count(DisposableEmailDomains::parse($contents)))->toBeGreaterThan(5000);
});

it('prefers the refreshed copy once it exists', function () {
    File::put($this->refreshed, "fresh-throwaway.example\n");

    expect((new DisposableEmailDomains)->isDisposable('a@fresh-throwaway.example'))->toBeTrue()
        ->and((new DisposableEmailDomains)->isDisposable('a@mailinator.com'))->toBeFalse();
});

it('refreshes the list into storage', function () {
    Http::fake(['raw.githubusercontent.com/*' => Http::response(implode("\n", array_map(fn ($i) => "throwaway{$i}.example", range(1, 1200))))]);

    $this->artisan('auth:refresh-disposable-domains')->expectsOutputToContain('Saved 1200 disposable domains.')->assertSuccessful();

    expect(File::get($this->refreshed))->toContain('throwaway1200.example');
});

it('keeps the current list when the download fails or looks broken', function (Closure $fake) {
    $fake();

    $this->artisan('auth:refresh-disposable-domains')->assertFailed();

    expect(File::exists($this->refreshed))->toBeFalse();
})->with([
    'server error' => [fn () => Http::fake(['*' => Http::response('', 500)])],
    'too short' => [fn () => Http::fake(['*' => Http::response("one.example\ntwo.example")])],
]);

it('runs on the 3rd of each month', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'auth:refresh-disposable-domains'));

    expect($event?->expression)->toBe('20 4 3 * *');
});
