<?php

use App\Support\Seo\PageMeta;
use Tests\TestCase;

uses(TestCase::class);

it('falls back to the app name and default description', function () {
    $meta = PageMeta::defaults();

    expect($meta->fullTitle())->toBe(config('app.name'))
        ->and($meta->resolvedDescription())->toBe(config('platform.seo.default_description'))
        ->and($meta->resolvedImage())->toBeNull();
});

it('suffixes page titles with the app name', function () {
    expect((new PageMeta(title: 'Bases'))->fullTitle())->toBe('Bases · '.config('app.name'));
});

it('makes canonical and image URLs absolute', function () {
    $meta = new PageMeta(canonical: '/bases/th17', image: '/og/base.png');

    expect($meta->resolvedCanonical())->toBe(url('/bases/th17'))
        ->and($meta->resolvedImage())->toBe(url('/og/base.png'));
});
