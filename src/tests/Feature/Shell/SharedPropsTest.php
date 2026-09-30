<?php

use Inertia\Testing\AssertableInertia as Assert;

it('shares only the allowlisted props with guests', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('auth.user', null)
        ->where('auth.can', [])
        ->where('flash.success', null)
        ->where('flash.error', null)
        ->where('unreadCount', null)
        ->where('features', [])
    );
});

it('shares flash messages from the session', function () {
    $this->withSession(['success' => 'Saved'])->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('flash.success', 'Saved'));
});

it('never exposes private keys in shared props', function () {
    $props = $this->get('/')->viewData('page')['props'];

    expect(array_keys($props))->toEqualCanonicalizing(['errors', 'auth', 'flash', 'unreadCount', 'features', 'meta'])
        ->and(json_encode($props))->not->toContain('email', 'password', 'ip', 'role', 'token');
});
