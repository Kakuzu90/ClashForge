<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the home page through Inertia', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Home/Index'));
});

it('skips SSR on excluded paths only', function (string $path, bool $ssr) {
    Route::middleware('web')->get($path, fn () => Inertia::render('Home/Index'));
    config(['inertia.ssr.enabled' => true]);
    // The renderer is down, so a public page falls back to the client shell.
    Http::fake(['*/render' => Http::response('', 503)]);

    $this->get($path)->assertOk();

    expect(config('inertia.ssr.enabled'))->toBe($ssr);
})->with([
    'settings area' => ['/settings/profile', false],
    'admin area' => ['/admin/reports', false],
    'public page' => ['/bases/demo', true],
]);
