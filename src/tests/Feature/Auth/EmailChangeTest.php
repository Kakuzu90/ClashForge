<?php

use App\Domain\Auth\Events\EmailVerified;
use App\Domain\Auth\Notifications\EmailChangeAttemptNotification;
use App\Domain\Auth\Notifications\EmailChangedNotification;
use App\Domain\Auth\Notifications\EmailChangeLinkNotification;
use App\Domain\Auth\Notifications\EmailChangeRejectedNotification;
use App\Domain\Auth\Notifications\VerifyEmailNotification;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Auth\CapturesSecurityLog;
use Tests\Support\Auth\InteractsWithBrowsers;

// FR-AUTH-8 at /settings/security: the new address gets a signed 60-minute link and changes
// nothing until the signed-in account confirms it; the old address gets a notice; a taken address
// answers the same and is told by email only (specs/23 §1).

uses(CapturesSecurityLog::class, InteractsWithBrowsers::class);

beforeEach(function () {
    $this->captureSecurityLog();
    Notification::fake();
    Date::setTestNow('2026-10-01 12:00:00');
    $this->user = User::factory()->create(['username' => 'chief', 'email' => 'chief@example.com', 'password' => 'password']);
});

/**
 * A browser that confirmed its password just now (the 15-minute window).
 */
function confirmed(User $user): mixed
{
    return test()->actingAs($user)->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()]);
}

function changeLink(User $user, string $email): string
{
    return substr(EmailChangeLinkNotification::url($user->ulid, $email), strlen(config('app.url')));
}

it('shows the masked address and no pending change on the Security page', function () {
    $this->actingAs($this->user)->get('/settings/security')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Security')
            ->where('email', 'c***@example.com')
            ->where('pendingEmail', null)
            ->missing('emailNeedsPassword')
            ->where('linkMinutes', config('platform.auth.verification_link_minutes')));
});

it('takes the current password with the change, never sending anyone to the confirm page', function () {
    $this->actingAs($this->user)->from('/settings/security')->put('/settings/security/email', ['email' => 'new@example.com', 'current_password' => 'wrong'])
        ->assertRedirect('/settings/security')
        ->assertSessionHasErrors(['current_password' => 'That is not your current password.']);
    $this->actingAs($this->user)->from('/settings/security')->put('/settings/security/email', ['email' => 'new@example.com'])
        ->assertSessionHasErrors(['current_password' => 'The current password field is required.']);

    expect($this->user->refresh()->pending_email)->toBeNull();
    Notification::assertNothingSent();
    expect(collect($this->securityEvents())->pluck('message'))->toContain('auth.password_confirm_failed');

    $this->actingAs($this->user)->from('/settings/security')->put('/settings/security/email', ['email' => 'new@example.com', 'current_password' => 'password'])
        ->assertRedirect('/settings/security')
        ->assertSessionHas('auth.password_confirmed_at');
    expect($this->user->refresh()->pending_email)->toBe('new@example.com');
});

it('stores the new address as pending and sends the link to it, changing nothing yet', function () {
    confirmed($this->user)->from('/settings/security')->put('/settings/security/email', ['current_password' => 'password', 'email' => ' New@Example.com '])
        ->assertRedirect('/settings/security')
        ->assertSessionHas('success', 'Check N***@Example.com for a link to confirm the change. It expires in 60 minutes.');

    $user = $this->user->refresh();
    expect($user->email)->toBe('chief@example.com')
        ->and($user->pending_email)->toBe('New@Example.com')
        ->and($user->pending_email_requested_at?->toIso8601String())->toBe(now()->toIso8601String());

    Notification::assertSentOnDemand(EmailChangeLinkNotification::class, fn ($n, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'New@Example.com' && $n->ulid === $user->ulid);
    expect(collect($this->securityEvents())->pluck('message'))->toContain('auth.email_change_requested');

    $this->get('/settings/security')->assertInertia(fn (Assert $page) => $page->where('pendingEmail', 'N***@Example.com')->where('email', 'c***@example.com'));
});

it('refuses the current address as a field error', function () {
    confirmed($this->user)->from('/settings/security')->put('/settings/security/email', ['current_password' => 'password', 'email' => 'CHIEF@example.com'])
        ->assertSessionHasErrors(['email' => 'That is already your email address.']);

    expect($this->user->refresh()->pending_email)->toBeNull();
});

it('validates the address like registration', function (mixed $email, string $message) {
    confirmed($this->user)->from('/settings/security')->put('/settings/security/email', ['current_password' => 'password', 'email' => $email])
        ->assertSessionHasErrors(['email' => $message]);
})->with([
    'missing' => ['', 'The new email field is required.'],
    'not an email' => ['not-an-email', 'Enter a valid email address.'],
    'too long' => [str_repeat('a', 250).'@example.com', 'The new email field must not be greater than 255 characters.'],
    'array' => [['a@example.com'], 'The new email field must be a string.'],
    'disposable' => ['someone@mailinator.com', 'Use an email address you will keep. Throwaway inboxes are not accepted.'],
]);

it('answers a taken address exactly like a free one, and tells both inboxes instead', function () {
    $owner = User::factory()->create(['email' => 'taken@example.com']);

    confirmed($this->user)->from('/settings/security')->put('/settings/security/email', ['current_password' => 'password', 'email' => 'taken@example.com'])
        ->assertRedirect('/settings/security')
        ->assertSessionHas('success', 'Check t***@example.com for a link to confirm the change. It expires in 60 minutes.');

    expect($this->user->refresh()->email)->toBe('chief@example.com')
        ->and($owner->refresh()->email)->toBe('taken@example.com');
    Notification::assertSentOnDemandTimes(EmailChangeLinkNotification::class, 0);
    Notification::assertSentTo($owner, EmailChangeAttemptNotification::class);
    Notification::assertSentTo($this->user, EmailChangeRejectedNotification::class, fn ($n) => $n->newEmail === 't***@example.com');
});

it('counts a soft-deleted account as holding its address', function () {
    $deleted = User::factory()->create(['email' => 'gone@example.com']);
    $deleted->delete();

    confirmed($this->user)->put('/settings/security/email', ['current_password' => 'password', 'email' => 'gone@example.com']);

    Notification::assertSentOnDemandTimes(EmailChangeLinkNotification::class, 0);
    Notification::assertSentTo($deleted, EmailChangeAttemptNotification::class);
});

it('sends each taken-address notice at most once an hour', function () {
    $owner = User::factory()->create(['email' => 'taken@example.com']);

    confirmed($this->user)->put('/settings/security/email', ['current_password' => 'password', 'email' => 'taken@example.com']);
    confirmed($this->user)->post('/settings/security/email/resend', ['current_password' => 'password']);

    Notification::assertSentToTimes($owner, EmailChangeAttemptNotification::class, 1);
    Notification::assertSentToTimes($this->user, EmailChangeRejectedNotification::class, 1);

    $this->travel(61)->minutes();
    confirmed($this->user)->post('/settings/security/email/resend', ['current_password' => 'password']);

    Notification::assertSentToTimes($owner, EmailChangeAttemptNotification::class, 2);
});

it('replaces an earlier pending address, so the earlier link stops working', function () {
    $user = User::factory()->withPendingEmail('first@example.com')->create();
    $first = changeLink($user, 'first@example.com');

    confirmed($user)->put('/settings/security/email', ['current_password' => 'password', 'email' => 'second@example.com']);

    expect($user->refresh()->pending_email)->toBe('second@example.com');
    $this->get($first)->assertInertia(fn (Assert $page) => $page->component('Settings/EmailChangeConfirm')->where('outcome', 'invalid')->where('confirmUrl', null));
});

it('sends the link again for the pending address', function () {
    $user = User::factory()->withPendingEmail('new@example.com')->create();

    confirmed($user)->from('/settings/security')->post('/settings/security/email/resend', ['current_password' => 'password'])
        ->assertRedirect('/settings/security')
        ->assertSessionHas('success');

    Notification::assertSentOnDemand(EmailChangeLinkNotification::class, fn ($n, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'new@example.com');
});

it('takes the current password with a resend too, so a copied cookie cannot finish a stale change', function () {
    $user = User::factory()->withPendingEmail('new@example.com')->create();

    $this->actingAs($user)->from('/settings/security')->post('/settings/security/email/resend', ['current_password' => 'wrong'])
        ->assertSessionHasErrors(['current_password' => 'That is not your current password.']);
    $this->actingAs($user)->from('/settings/security')->post('/settings/security/email/resend')
        ->assertSessionHasErrors('current_password');

    Notification::assertNothingSent();
});

it('sends any one address at most a few links an hour, from every account together', function () {
    $limit = (int) config('platform.auth.email_change_links_per_address_per_hour');

    foreach (range(1, $limit + 1) as $i) {
        confirmed(User::factory()->create())->from('/settings/security')->put('/settings/security/email', ['current_password' => 'password', 'email' => 'target@example.com'])
            ->assertSessionHas('success');
    }

    Notification::assertSentOnDemandTimes(EmailChangeLinkNotification::class, $limit);
});

it('does nothing on resend when nothing is pending', function () {
    confirmed($this->user)->from('/settings/security')->post('/settings/security/email/resend', ['current_password' => 'password'])
        ->assertRedirect('/settings/security')
        ->assertSessionMissing('success');

    Notification::assertNothingSent();
});

it('cancels a pending change', function () {
    $user = User::factory()->withPendingEmail('new@example.com')->create();
    $link = changeLink($user, 'new@example.com');

    $this->actingAs($user)->from('/settings/security')->delete('/settings/security/email')
        ->assertRedirect('/settings/security')
        ->assertSessionHas('success', 'Email change cancelled. Your email has not changed.');

    expect($user->refresh()->pending_email)->toBeNull()->and($user->pending_email_requested_at)->toBeNull();
    $this->get($link)->assertInertia(fn (Assert $page) => $page->where('outcome', 'invalid'));
});

it('opens a page naming the account and the masked new address, and changes nothing', function () {
    $user = User::factory()->withPendingEmail('new@example.com')->create(['username' => 'raider']);
    $path = changeLink($user, 'new@example.com');

    $this->actingAs($user)->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/EmailChangeConfirm')
            ->where('outcome', 'pending')
            ->where('username', 'raider')
            ->where('newEmail', 'n***@example.com')
            ->where('confirmUrl', config('app.url').$path)
            ->where('meta.title', 'Confirm your new email'));

    expect($user->refresh()->email)->not->toBe('new@example.com')->and($user->pending_email)->toBe('new@example.com');
});

it('sends a guest to sign in and back to the link', function () {
    $user = User::factory()->withPendingEmail('new@example.com')->create(['email' => 'old@example.com', 'password' => 'password']);
    $path = changeLink($user, 'new@example.com');

    $this->get($path)->assertRedirect('/login');
    $this->post('/login', ['email' => 'old@example.com', 'password' => 'password'])->assertRedirect(config('app.url').$path);
});

it('changes the email on the button, once, and tells both addresses', function () {
    Event::fake([EmailVerified::class]);
    $user = User::factory()->withPendingEmail('new@example.com')->create(['username' => 'raider', 'email' => 'old@example.com', 'email_verified_at' => now()->subYear()]);
    $path = changeLink($user, 'new@example.com');

    $this->actingAs($user)->post($path)->assertRedirect('/settings/email/confirmed');
    $this->get('/settings/email/confirmed')->assertInertia(fn (Assert $page) => $page
        ->component('Settings/EmailChangeConfirm')
        ->where('outcome', 'changed')
        ->where('message', 'Your email is changed. Every other device was signed out.')
        ->where('confirmUrl', null));

    $user->refresh();
    expect($user->email)->toBe('new@example.com')
        ->and($user->email_verified_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and($user->pending_email)->toBeNull()
        ->and($user->pending_email_requested_at)->toBeNull();

    Notification::assertSentOnDemand(EmailChangedNotification::class, fn ($n, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'old@example.com' && $n->toOldAddress && $n->newEmail === 'n***@example.com');
    Notification::assertSentTo($user, EmailChangedNotification::class, fn ($n) => ! $n->toOldAddress);
    Event::assertNotDispatched(EmailVerified::class);
    expect(collect($this->securityEvents())->pluck('message'))->toContain('auth.email_changed');

    $this->post($path)->assertRedirect('/settings/email/confirmed');
    $this->get('/settings/email/confirmed')->assertInertia(fn (Assert $page) => $page->where('outcome', 'already_changed'));
    Notification::assertSentOnDemandTimes(EmailChangedNotification::class, 1);
});

it('says so when the link is opened after the change', function () {
    $user = User::factory()->withPendingEmail('new@example.com')->create();
    $path = changeLink($user, 'new@example.com');
    $this->actingAs($user)->post($path);

    $this->get($path)->assertInertia(fn (Assert $page) => $page->where('outcome', 'already_changed')->where('message', 'This email is already on your account.'));
});

it('verifies an account that never confirmed its first address', function () {
    Event::fake([EmailVerified::class]);
    $user = User::factory()->unverified()->withPendingEmail('fixed@example.com')->create(['email' => 'typo@exmaple.com']);
    $oldLink = substr(VerifyEmailNotification::url($user), strlen(config('app.url')));

    $this->actingAs($user)->post(changeLink($user, 'fixed@example.com'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
    Event::assertDispatchedTimes(EmailVerified::class, 1);
    $this->get($oldLink)->assertInertia(fn (Assert $page) => $page->component('Auth/VerificationResult')->where('outcome', 'invalid'));
});

it('keeps this browser signed in and signs every other one out', function () {
    $this->useDatabaseSessions();
    $user = User::factory()->withPendingEmail('new@example.com')->create(['email' => 'old@example.com', 'password' => 'password', 'last_login_at' => now()->subDay()]);
    $path = changeLink($user, 'new@example.com');
    $other = $this->signInBrowser($user, remember: true);
    $mine = $this->signInBrowser($user);
    $token = $user->refresh()->remember_token;

    $this->browser($mine)->post($path)->assertRedirect('/settings/email/confirmed');

    expect($user->refresh()->remember_token)->not->toBe($token)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1);
    $this->browser($other)->get('/settings/profile')->assertRedirect('/login');
});

it('refuses the change when another account took the address before confirming', function () {
    $user = User::factory()->withPendingEmail('new@example.com')->create(['email' => 'old@example.com']);
    $path = changeLink($user, 'new@example.com');
    User::factory()->create(['email' => 'new@example.com']);

    $this->actingAs($user)->post($path);
    $this->get('/settings/email/confirmed')->assertInertia(fn (Assert $page) => $page
        ->where('outcome', 'taken')
        ->where('message', 'That address belongs to another account, so your email has not changed.'));

    expect($user->refresh()->email)->toBe('old@example.com')->and($user->pending_email)->toBeNull();
    Notification::assertSentOnDemandTimes(EmailChangedNotification::class, 0);
});

it('lets the first of two accounts waiting on one address have it', function () {
    $first = User::factory()->withPendingEmail('shared@example.com')->create();
    $second = User::factory()->withPendingEmail('shared@example.com')->create();

    $this->actingAs($first)->post(changeLink($first, 'shared@example.com'));
    $this->actingAs($second)->post(changeLink($second, 'shared@example.com'));

    expect($first->refresh()->email)->toBe('shared@example.com')
        ->and($second->refresh()->email)->not->toBe('shared@example.com');
    $this->actingAs($second)->get('/settings/email/confirmed')->assertInertia(fn (Assert $page) => $page->where('outcome', 'taken'));
});

it('does not confirm for another account signed in on this browser', function () {
    $user = User::factory()->withPendingEmail('new@example.com')->create();
    $path = changeLink($user, 'new@example.com');
    $other = User::factory()->withPendingEmail('new@example.com')->create();

    $this->actingAs($other)->get($path)->assertInertia(fn (Assert $page) => $page
        ->where('outcome', 'wrong_account')
        ->where('username', null)
        ->where('newEmail', null)
        ->where('confirmUrl', null));
    $this->actingAs($other)->post($path);

    expect($user->refresh()->pending_email)->toBe('new@example.com')
        ->and($other->refresh()->email)->not->toBe('new@example.com');
});

it('refuses an expired, tampered or unsigned link with one message, on open and on confirm', function (Closure $path) {
    $user = User::factory()->withPendingEmail('new@example.com')->create();
    $target = $path($user);

    $this->actingAs($user)->get($target)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('outcome', 'invalid')
        ->where('message', 'This link has already been used or has expired. Ask for a new one in your security settings.')
        ->where('confirmUrl', null));
    $this->post($target)->assertRedirect('/settings/email/confirmed');
    $this->get('/settings/email/confirmed')->assertInertia(fn (Assert $page) => $page->where('outcome', 'invalid'));

    expect($user->refresh()->pending_email)->toBe('new@example.com');
})->with([
    'expired' => [function (User $user) {
        $path = changeLink($user, 'new@example.com');
        Date::setTestNow(now()->addMinutes((int) config('platform.auth.verification_link_minutes'))->addSecond());

        return $path;
    }],
    'signature changed' => [fn (User $user) => preg_replace('/signature=[0-9a-f]+/', 'signature='.str_repeat('0', 64), changeLink($user, 'new@example.com'))],
    'unsigned' => [fn (User $user) => '/settings/email/confirm/'.$user->ulid.'/'.sha1('new@example.com')],
    'other address' => [fn (User $user) => changeLink($user, 'other@example.com')],
]);

it('sends a visit to the result page without a result to the Security page', function () {
    $this->actingAs($this->user)->get('/settings/email/confirmed')->assertRedirect('/settings/security');
});

it('keeps the email-change limits in config', function () {
    expect(config('platform.auth.email_change_per_hour'))->toBe(3)
        ->and(config('platform.auth.email_change_notice_per_hour'))->toBe(1)
        ->and(config('platform.auth.email_change_links_per_address_per_hour'))->toBe(3)
        ->and(config('platform.auth.verification_link_minutes'))->toBe(60);
});
