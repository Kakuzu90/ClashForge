<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the component gallery outside production', function () {
    $this->get('/dev/components')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dev/Components'));
});

it('hides the component gallery in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/dev/components')->assertNotFound();
});
