<?php

use App\Domain\Auth\Events\EmailVerified;
use App\Domain\Auth\Notifications\VerifyEmailNotification;
use App\Domain\Notifications\Models\Notification as InAppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Auth\CapturesSecurityLog;
use Tests\Support\Auth\InteractsWithBrowsers;

// FR-AUTH-3/4, FR-NOTIF-2 "email verified": the signed 60-minute link opens a page naming the
// account; its button (a POST) confirms, so a mail gateway that opens links confirms nothing.

uses(CapturesSecurityLog::class, InteractsWithBrowsers::class);

beforeEach(function () {
    $this->captureSecurityLog();
    Date::setTestNow('2026-10-01 12:00:00');
    $this->user = User::factory()->unverified()->create(['username' => 'chief', 'email' => 'Chief@Example.com', 'password' => 'password']);
});

function linkPath(User $user): string
{
    return substr(VerifyEmailNotification::url($user), strlen(config('app.url')));
}

it('opens a page naming the account, and changes nothing', function () {
    Event::fake([EmailVerified::class]);
    $path = linkPath($this->user);

    $this->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/VerificationResult')
            ->where('outcome', 'pending')
            ->where('username', 'chief')
            ->where('confirmUrl', config('app.url').$path)
            ->where('signedIn', false));

    expect($this->user->refresh()->email_verified_at)->toBeNull();
    Event::assertNotDispatched(EmailVerified::class);
});

it('confirms on the button, once, and shows the result', function () {
    Event::fake([EmailVerified::class]);
    $path = linkPath($this->user);

    $this->post($path)->assertRedirect('/email/verified');
    $this->get('/email/verified')
        ->assertInertia(fn (Assert $page) => $page
            ->where('outcome', 'verified')
            ->where('message', 'Your email is confirmed.')
            ->where('signedIn', false)
            ->where('username', null)
            ->where('confirmUrl', null));

    expect($this->user->refresh()->email_verified_at?->toIso8601String())->toBe(now()->toIso8601String());
    $this->assertGuest();
    Event::assertDispatchedTimes(EmailVerified::class, 1);
    expect(collect($this->securityEvents())->pluck('message'))->toContain('auth.email_verified');

    $this->post($path)->assertRedirect('/email/verified');
    $this->get('/email/verified')->assertInertia(fn (Assert $page) => $page->where('outcome', 'already_verified'));
    Event::assertDispatchedTimes(EmailVerified::class, 1);
});

it('says so when the link is opened after confirming', function () {
    $path = linkPath($this->user);
    $this->post($path);

    $this->get($path)->assertInertia(fn (Assert $page) => $page->where('outcome', 'already_verified')->where('message', 'Your email is already confirmed.')->where('confirmUrl', null));
});

it('lets a browser signed in to the account carry on, and signs every other one out', function () {
    $this->useDatabaseSessions();
    $other = $this->signInBrowser($this->user);
    $mine = $this->signInBrowser($this->user);

    $this->browser($mine)->post(linkPath($this->user))->assertRedirect('/email/verified');
    $this->browser($mine)->get('/email/verified')->assertInertia(fn (Assert $page) => $page->where('outcome', 'verified')->where('signedIn', true));

    $this->browser($mine)->get('/settings/profile')->assertOk();
    $this->browser($other)->get('/settings/profile')->assertRedirect('/login');
});

it('signs out every browser of the account when confirmed from elsewhere', function () {
    $this->useDatabaseSessions();
    $this->signInBrowser($this->user);

    $this->post(linkPath($this->user));

    expect(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(0);
});

it('confirms but does not touch another account that happens to be signed in', function () {
    $other = User::factory()->create();

    $this->actingAs($other)->post(linkPath($this->user));

    $this->assertAuthenticatedAs($other);
    expect($this->user->refresh()->email_verified_at)->not->toBeNull();
    $this->actingAs($other)->get('/email/verified')->assertInertia(fn (Assert $page) => $page->where('signedIn', false));
});

it('refuses an expired, tampered or mismatched link with one message, on open and on confirm', function (Closure $path) {
    $target = $path($this->user);

    $this->get($target)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('outcome', 'invalid')
        ->where('message', 'This link has already been used or has expired. Ask for a new one.')
        ->where('confirmUrl', null));
    $this->post($target)->assertRedirect('/email/verified');
    $this->get('/email/verified')->assertInertia(fn (Assert $page) => $page->where('outcome', 'invalid'));

    expect($this->user->refresh()->email_verified_at)->toBeNull();
})->with([
    'expired' => [function (User $user) {
        $path = linkPath($user);
        Date::setTestNow(now()->addMinutes((int) config('platform.auth.verification_link_minutes'))->addSecond());

        return $path;
    }],
    'signature changed' => [fn (User $user) => preg_replace('/signature=[0-9a-f]+/', 'signature='.str_repeat('0', 64), linkPath($user))],
    'unsigned' => [fn (User $user) => '/email/verify/'.$user->ulid.'/'.sha1('chief@example.com')],
    'old address' => [function (User $user) {
        $path = linkPath($user);
        $user->forceFill(['email' => 'new@example.com'])->save();

        return $path;
    }],
]);

it('sends a visit to the result page without a result home', function () {
    $this->get('/email/verified')->assertRedirect('/');
});

it('writes the in-app "email confirmed" notice', function () {
    $this->post(linkPath($this->user));

    expect(InAppNotification::query()->where('notifiable_id', $this->user->id)->sole()->type)->toBe('email_verified');
});

it('shows the notice page with the address masked', function () {
    $this->actingAs($this->user)->get('/email/verify')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/VerifyEmail')
            ->where('email', 'C***@Example.com')
            ->where('status', null)
            ->where('linkMinutes', 60));

    expect(json_encode($this->actingAs($this->user)->get('/email/verify')->viewData('page')['props']))->not->toContain('Chief@Example.com');
});

it('sends a verified account home from the notice page and the resend', function () {
    Notification::fake();
    $verified = User::factory()->create();

    $this->actingAs($verified)->get('/email/verify')->assertRedirect('/');
    $this->actingAs($verified)->post('/email/verification-notification')->assertRedirect('/');

    Notification::assertNothingSent();
});

it('sends guests to sign in from the notice page', function () {
    $this->get('/email/verify')->assertRedirect('/login');
    $this->post('/email/verification-notification')->assertRedirect('/login');
});

it('resends a link, limited per account', function () {
    Notification::fake();
    $limit = (int) config('platform.auth.verify_resend_per_hour');

    foreach (range(1, $limit) as $i) {
        $this->actingAs($this->user)->from('/email/verify')->post('/email/verification-notification')
            ->assertRedirect('/email/verify')
            ->assertSessionHas('status', 'A new link is on its way. It expires in 60 minutes.');
    }

    $this->actingAs($this->user)->from('/email/verify')->post('/email/verification-notification')
        ->assertRedirect('/email/verify')
        ->assertSessionHas('error', 'You asked for a new link a few times already. Try again in an hour.');

    Notification::assertSentToTimes($this->user, VerifyEmailNotification::class, $limit);
});

it('shares whether the signed-in account has confirmed its email', function () {
    $this->actingAs($this->user)->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->where('auth.user.emailVerified', false));
});
