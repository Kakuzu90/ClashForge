<?php

use App\Models\User;
use App\Support\Privacy\IpHash;
use Tests\Support\Auth\CapturesSecurityLog;

// specs/11 §3: permission denials reach the `security` log, with the ULID and a hashed IP.

uses(CapturesSecurityLog::class);

beforeEach(fn () => $this->captureSecurityLog());

it('logs a Gate denial', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/admin')->assertForbidden();

    expect($this->securityEvents())->toBe([[
        'message' => 'auth.permission_denied',
        'context' => ['user' => $user->ulid, 'ip_hash' => IpHash::of('203.0.113.9'), 'route' => 'admin.dashboard'],
    ]]);
});

it('logs a write blocked by account status', function () {
    $user = User::factory()->pendingDeletion()->create();

    $this->actingAs($user)->postJson('/uploads/intent')->assertForbidden();

    expect($this->securityEvents()[0])->toMatchArray(['message' => 'auth.permission_denied'])
        ->and($this->securityEvents()[0]['context'])->toMatchArray(['user' => $user->ulid, 'reason' => 'status:pending_deletion', 'route' => 'uploads.intent']);
});

it('logs a banned session being ended', function () {
    $user = User::factory()->banned()->create();

    $this->actingAs($user)->get('/');

    expect(array_column($this->securityEvents(), 'message'))->toContain('auth.banned_session_ended');
});

it('caps denial lines per account and route, so a loop cannot flood the log', function () {
    $user = User::factory()->create();
    $cap = (int) config('platform.security_log.denials_per_minute');

    for ($i = 0; $i < $cap + 5; $i++) {
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    expect($this->securityEvents())->toHaveCount($cap);
});
