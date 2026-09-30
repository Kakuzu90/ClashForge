<?php

use Inertia\Testing\AssertableInertia as Assert;

it('previews each layout outside production', function (string $layout, string $component) {
    $this->get("/dev/layouts/{$layout}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['public', 'Dev/LayoutPublic'],
    ['app', 'Dev/LayoutApp'],
    ['admin', 'Dev/LayoutAdmin'],
]);

it('rejects unknown layouts', function () {
    $this->get('/dev/layouts/other')->assertNotFound();
});

it('hides layout previews in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/dev/layouts/app')->assertNotFound();
});
