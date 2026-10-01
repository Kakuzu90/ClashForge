<?php

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Notifications\EmailChangeLinkNotification;
use App\Domain\Auth\Services\EmailChangeService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Auth\CapturesSecurityLog;

// specs/11 on the email change (FR-AUTH-8): a taken address answers exactly like a free one,
// privileged fields are ignored, writes follow account status, the 15-minute re-confirmation and
// the `email-change` limiter hold, and no page carries a full address.

uses(CapturesSecurityLog::class);

beforeEach(function () {
    $this->captureSecurityLog();
    Notification::fake();
    Date::setTestNow('2026-10-01 12:00:00');
});

function confirmedAs(User $user): mixed
{
    return test()->actingAs($user)->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()]);
}

/**
 * What a client can observe about one request and the page it lands on.
 *
 * @return array<string, mixed>
 */
function observed(TestResponse $response, User $user): array
{
    $page = test()->actingAs($user)->get('/settings/security');
    $props = $page->viewData('page')['props'];
    unset($props['errors'], $props['auth'], $props['sessions']);

    return [
        'status' => $response->getStatusCode(),
        'location' => $response->headers->get('Location'),
        'flash' => str_replace(['f***@example.com', 't***@example.com'], 'X', (string) session('success')),
        'cookies' => collect($response->headers->getCookies())->map->getName()->sort()->values()->all(),
        'props' => str_replace(['f***@example.com', 't***@example.com'], 'X', (string) json_encode($props)),
    ];
}

it('answers a taken address exactly like a free one', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $free = User::factory()->create(['email' => 'one@example.com']);
    $taken = User::factory()->create(['email' => 'other@example.com']);

    $a = observed(confirmedAs($free)->from('/settings/security')->put('/settings/security/email', ['current_password' => 'password', 'email' => 'free@example.com']), $free);
    $b = observed(confirmedAs($taken)->from('/settings/security')->put('/settings/security/email', ['current_password' => 'password', 'email' => 'taken@example.com']), $taken);

    expect($b)->toEqual($a);
});

it('ignores privileged fields posted with an email change', function () {
    $user = User::factory()->restricted()->unverified()->create(['email' => 'old@example.com']);

    confirmedAs($user)->put('/settings/security/email', ['current_password' => 'password',
        'email' => 'new@example.com',
        'role' => 'admin',
        'status' => 'active',
        'email_verified_at' => now()->toIso8601String(),
        'pending_email' => 'other@example.com',
        'user_id' => 999,
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->role)->toBe(Role::User)
        ->and($user->status)->toBe(UserStatus::Restricted)
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->email)->toBe('old@example.com')
        ->and($user->pending_email)->toBe('new@example.com');
});

it('lets email writes follow account status', function (string $state, bool $allowed) {
    $user = User::factory()->{$state}()->withPendingEmail('pending@example.com')->create();
    $link = substr(EmailChangeLinkNotification::url($user->ulid, 'pending@example.com'), strlen(config('app.url')));

    $responses = [
        confirmedAs($user)->post('/settings/security/email/resend', ['current_password' => 'password']),
        confirmedAs($user)->post($link),
        confirmedAs($user)->put('/settings/security/email', ['current_password' => 'password', 'email' => 'new@example.com']),
        confirmedAs($user)->delete('/settings/security/email'),
    ];

    if ($allowed) {
        expect($user->refresh()->email)->toBe('pending@example.com')->and($user->pending_email)->toBeNull();
    } else {
        foreach ($responses as $response) {
            expect($response->getStatusCode())->toBe(403);
        }
        expect($user->refresh()->pending_email)->toBe('pending@example.com');
        Notification::assertNothingSent();
    }
})->with([
    'active' => ['admin', true],
    'restricted' => ['restricted', true],
    'unverified' => ['unverified', true],
    'suspended' => ['suspended', false],
    'pending deletion' => ['pendingDeletion', false],
]);

it('authorizes in the service too, not only in the middleware', function () {
    $user = User::factory()->create();
    $suspended = User::factory()->suspended()->withPendingEmail('pending@example.com')->create();

    expect(Gate::forUser($user)->allows('changeEmail', $user))->toBeTrue()
        ->and(Gate::forUser(User::factory()->superAdmin()->create())->allows('changeEmail', $user))->toBeFalse();

    $service = app(EmailChangeService::class);
    expect(fn () => $service->request($suspended, 'new@example.com', 'password', null))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->resend($suspended, 'password', null))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->cancel($suspended))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->confirm($suspended->ulid, sha1('pending@example.com'), $suspended, null, null))->toThrow(AuthorizationException::class);
    expect($suspended->refresh()->pending_email)->toBe('pending@example.com');
});

it('shares the password-guess limit with the confirm page and the password form', function () {
    $user = User::factory()->create();
    $limit = (int) config('platform.auth.password_confirm_per_minute');

    for ($i = 0; $i < $limit; $i++) {
        $this->actingAs($user)->put('/settings/security/email', ['email' => 'new@example.com', 'current_password' => 'wrong']);
    }

    $this->actingAs($user)->from('/settings/security')->put('/settings/security/email', ['email' => 'new@example.com', 'current_password' => 'password'])
        ->assertSessionHasErrors('current_password');
    expect($user->refresh()->pending_email)->toBeNull();
});

it('limits accepted requests and resends per account, as a field error with the wait', function () {
    $user = User::factory()->create();
    $limit = (int) config('platform.auth.email_change_per_hour');

    // Typos and the current address are free, as at registration.
    for ($i = 0; $i < $limit + 1; $i++) {
        confirmedAs($user)->put('/settings/security/email', ['current_password' => 'password', 'email' => 'not-an-email'])->assertSessionHasErrors(['email' => 'Enter a valid email address.']);
        $this->travel(61)->seconds();
    }
    confirmedAs($user)->put('/settings/security/email', ['current_password' => 'password', 'email' => $user->email])->assertSessionHasErrors(['email' => 'That is already your email address.']);
    $this->travel(61)->seconds();

    for ($i = 0; $i < $limit; $i++) {
        confirmedAs($user)->put('/settings/security/email', ['current_password' => 'password', 'email' => "new{$i}@example.com"])->assertSessionHasNoErrors();
        $this->travel(61)->seconds();
    }

    confirmedAs($user)->from('/settings/security')->put('/settings/security/email', ['current_password' => 'password', 'email' => 'late@example.com'])
        ->assertRedirect('/settings/security')
        ->assertSessionHasErrors('email');
    $this->travel(61)->seconds();
    confirmedAs($user)->from('/settings/security')->post('/settings/security/email/resend', ['current_password' => 'password'])
        ->assertSessionHasErrors('email');

    expect($user->refresh()->pending_email)->toBe('new'.($limit - 1).'@example.com');
    Notification::assertSentOnDemandTimes(EmailChangeLinkNotification::class, $limit);
    expect(collect($this->securityEvents())->where('message', 'auth.rate_limited')->pluck('context.limiter'))->toContain('email-change');
});

it('puts no full address on the Security page or the confirm page', function () {
    $user = User::factory()->withPendingEmail('pending-person@example.net')->create(['email' => 'current-person@example.com']);
    $link = substr(EmailChangeLinkNotification::url($user->ulid, 'pending-person@example.net'), strlen(config('app.url')));

    $pages = [
        $this->actingAs($user)->get('/settings/security'),
        $this->actingAs($user)->get($link),
    ];

    foreach ($pages as $page) {
        expect((string) $page->getContent())->not->toContain('current-person')->not->toContain('pending-person');
    }
    $pages[1]->assertInertia(fn (Assert $page) => $page->where('newEmail', 'p***@example.net'));
});

it('does not name the account behind another account\'s link', function () {
    $owner = User::factory()->withPendingEmail('new@example.com')->create(['username' => 'owner_name']);
    $link = substr(EmailChangeLinkNotification::url($owner->ulid, 'new@example.com'), strlen(config('app.url')));

    $body = (string) $this->actingAs(User::factory()->create())->get($link)->getContent();

    expect($body)->not->toContain('owner_name')->not->toContain('n***@example.com');
});
