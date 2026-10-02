<?php

use App\Models\User;
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

    expect(array_keys($props))->toEqualCanonicalizing(['errors', 'auth', 'flash', 'unreadCount', 'features', 'cocApi', 'meta'])
        ->and(json_encode($props))->not->toContain('email', 'password', 'ip', 'role', 'token');
});

it('shares the staff flags, never the role', function (string $state, bool $moderator, bool $admin) {
    $user = User::factory()->{$state}()->create();

    $response = $this->actingAs($user)->get('/');
    $response->assertInertia(fn (Assert $page) => $page->where('auth.can', ['accessAdmin' => $admin, 'viewReportQueue' => $moderator, 'viewUsers' => $admin, 'viewPlatformStats' => $admin, 'viewAuditLog' => $admin]));

    expect(json_encode($response->viewData('page')['props']['auth']))->not->toContain('role', $user->role->value === 'user' ? 'moderator' : $user->role->value);
})->with([
    'user' => ['unverified', false, false],
    'moderator' => ['moderator', true, false],
    'admin' => ['admin', true, true],
    'super admin' => ['superAdmin', true, true],
]);
