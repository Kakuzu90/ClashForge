<?php

use App\Domain\Audit\Models\AuditLog;
use App\Models\User;

// specs/04 §2 "View user accounts" (admin+, read: open to restricted and pending-deletion
// admins), specs/11 "Data exposure via page props" and §5 (email to admins only, IPs hashed).

it('sends a guest to sign in', function (string $url) {
    $this->get($url)->assertRedirect('/login');
})->with(['/admin/users', '/admin/users/01hzzzzzzzzzzzzzzzzzzzzzzz']);

it('refuses users and moderators on the list and the detail', function (string $role) {
    $viewer = User::factory()->{$role}()->create();
    $target = User::factory()->create();

    $this->actingAs($viewer)->get('/admin/users')->assertForbidden();
    $this->actingAs($viewer)->get('/admin/users/'.$target->ulid)->assertForbidden();
    $this->actingAs($viewer)->get('/admin/users?role=nope')->assertForbidden();
})->with(['unverified', 'moderator']);

it('opens for admins and super admins, including restricted and pending-deletion ones', function (string $role, ?string $state) {
    $factory = User::factory()->{$role}();
    $viewer = ($state === null ? $factory : $factory->{$state}())->create();
    $target = User::factory()->create();

    $this->actingAs($viewer)->get('/admin/users')->assertOk();
    $this->actingAs($viewer)->get('/admin/users/'.$target->ulid)->assertOk();
})->with([
    'admin' => ['admin', null],
    'super admin' => ['superAdmin', null],
    'restricted admin' => ['admin', 'restricted'],
    'pending-deletion admin' => ['admin', 'pendingDeletion'],
]);

it('sends suspended staff to the notice on both routes', function (string $role) {
    $viewer = User::factory()->{$role}()->suspended()->create();
    $target = User::factory()->create();

    $this->actingAs($viewer)->get('/admin/users')->assertRedirect('/account/suspended');
    $this->actingAs($viewer)->get('/admin/users/'.$target->ulid)->assertRedirect('/account/suspended');
})->with(['admin', 'moderator']);

it('signs a banned admin out on both routes', function (string $url) {
    $target = User::factory()->create(['username' => 'target']);

    $this->actingAs(User::factory()->admin()->banned()->create())
        ->get(str_replace('{ulid}', $target->ulid, $url))
        ->assertRedirect('/login');
    $this->assertGuest();
})->with(['/admin/users', '/admin/users/{ulid}']);

it('refuses a restricted moderator the admin area and the user list', function () {
    $viewer = User::factory()->moderator()->restricted()->create();

    $this->actingAs($viewer)->get('/admin')->assertForbidden();
    $this->actingAs($viewer)->get('/admin/users')->assertForbidden();
});

it('never puts secrets or IP data in the detail props, audit trail included', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create(['remember_token' => 'remember-me-token-value', 'last_login_ip_hash' => str_repeat('e', 64)]);
    AuditLog::factory()->by($admin)->on($target)->create([
        'ip_hash' => str_repeat('d', 64),
        'user_agent' => 'Sample-Agent/1.0',
        'request_id' => 'req-private-123',
        'context' => ['via' => 'web'],
    ]);

    $props = json_encode($this->actingAs($admin)->get('/admin/users/'.$target->ulid)->viewData('page')['props']);

    expect($props)->not->toContain($target->password)
        ->and($props)->not->toContain('remember-me-token-value')
        ->and($props)->not->toContain(str_repeat('e', 64))
        ->and($props)->not->toContain(str_repeat('d', 64))
        ->and($props)->not->toContain('Sample-Agent/1.0')
        ->and($props)->not->toContain('req-private-123')
        ->and($props)->not->toContain('password')
        ->and($props)->not->toContain('two_factor')
        ->and($props)->not->toContain('ip_hash');
});
