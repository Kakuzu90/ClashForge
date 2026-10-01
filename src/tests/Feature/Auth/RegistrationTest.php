<?php

use App\Domain\Auth\Events\UserRegistered;
use App\Domain\Auth\Notifications\RegistrationAttemptNotification;
use App\Domain\Auth\Notifications\VerifyEmailNotification;
use App\Domain\Users\Models\PrivacySettings;
use App\Domain\Users\Models\Profile;
use App\Domain\Users\Models\UserStats;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Auth\CapturesSecurityLog;
use Tests\Support\Auth\FakesHibp;
use Tests\Support\Auth\RegistersAccounts;

// FR-AUTH-1/2/3/11, specs/04 §4, specs/11 "Spam and fake accounts", specs/23 §1.

uses(CapturesSecurityLog::class, FakesHibp::class, RegistersAccounts::class);

beforeEach(function () {
    $this->fakeHibp(['password123456']);
    $this->captureSecurityLog();
    Notification::fake();
    Date::setTestNow('2026-10-01 12:00:00');
});

it('shows the form to guests with its limits, the site key and a form time', function () {
    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->where('meta.title', 'Create your account')
            ->where('usernameMin', 3)
            ->where('usernameMax', 20)
            ->where('passwordMin', (int) config('platform.auth.min_password_length'))
            ->where('turnstileSiteKey', config('services.turnstile.site_key'))
            ->has('formStarted'));
});

it('sends a signed-in visitor home', function () {
    $this->actingAs(User::factory()->create())->get('/register')->assertRedirect('/');
});

it('creates an unverified account with its profile rows, emails the link and signs nobody in', function () {
    Event::fake([UserRegistered::class]);

    $this->register()->assertRedirect('/register/sent');

    $user = User::query()->where('email', 'newcomer@example.com')->sole();
    expect($user->username)->toBe('newcomer')
        ->and($user->email_verified_at)->toBeNull()
        ->and(password_verify('a-long-passphrase', $user->password))->toBeTrue();
    $this->assertGuest();
    Notification::assertSentTo($user, VerifyEmailNotification::class);
    Event::assertDispatched(UserRegistered::class, fn (UserRegistered $event) => $event->userId === $user->id);
    expect(collect($this->securityEvents())->pluck('message'))->toContain('auth.registered');
});

it('creates the profile, privacy and stats rows from the event', function () {
    $this->register();
    $user = User::query()->where('email', 'newcomer@example.com')->sole();

    expect(Profile::query()->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(PrivacySettings::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and(UserStats::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('shows the same page for every outcome', function () {
    $this->get('/register/sent')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/RegisterSent')->where('linkMinutes', 60));
});

it('lowercases the username and trims the email', function () {
    $this->register(['username' => '  NewComer ', 'email' => '  newcomer@example.com '])->assertRedirect('/register/sent');

    expect(User::query()->where('username', 'newcomer')->where('email', 'newcomer@example.com')->exists())->toBeTrue();
});

it('rejects invalid input with field errors', function (array $input, string $field) {
    $this->register($input)->assertRedirect('/register')->assertSessionHasErrors($field);

    expect(User::query()->count())->toBe(0);
})->with([
    'no email' => [['email' => ''], 'email'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'disposable email' => [['email' => 'someone@mailinator.com'], 'email'],
    'disposable subdomain' => [['email' => 'someone@mx.mailinator.com'], 'email'],
    'short username' => [['username' => 'ab'], 'username'],
    'long username' => [['username' => str_repeat('a', 21)], 'username'],
    'username with a dash' => [['username' => 'new-comer'], 'username'],
    'reserved username' => [['username' => 'admin'], 'username'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'mismatched password' => [['password_confirmation' => 'something-else-entirely'], 'password'],
    'breached password' => [['password' => 'password123456', 'password_confirmation' => 'password123456'], 'password'],
]);

it('says why a username is refused', function () {
    User::factory()->create(['username' => 'chief']);

    $this->register(['username' => 'CHIEF'])->assertSessionHasErrors(['username' => 'That username is taken. Pick another.']);
    $this->register(['username' => 'support'])->assertSessionHasErrors(['username' => 'That username is reserved. Pick another.']);
    $this->register(['username' => 'new.comer'])->assertSessionHasErrors(['username' => 'Use lowercase letters, numbers and underscores only.']);
    $this->register(['email' => 'x@guerrillamail.com'])->assertSessionHasErrors(['email' => 'Use an email address you will keep. Throwaway inboxes are not accepted.']);
});

it('counts deleted accounts\' usernames as taken', function () {
    User::factory()->create(['username' => 'chief'])->delete();

    $this->register(['username' => 'chief'])->assertSessionHasErrors('username');
});

it('refuses a failed Turnstile check, asking Cloudflare only once the rest is valid', function () {
    $turnstile = $this->turnstile(pass: false);

    $this->register(['username' => 'ab'])->assertSessionHasErrors('username');
    expect($turnstile->tokens)->toBe([]);

    $this->register()->assertSessionHasErrors(['turnstile_token' => 'We could not confirm you are not a bot. Try again.']);
    expect($turnstile->tokens)->toBe(['token'])
        ->and(User::query()->count())->toBe(0);
});

it('answers a taken email exactly like a new one and emails its owner instead', function () {
    $owner = User::factory()->create(['email' => 'chief@example.com']);

    $this->register(['email' => 'CHIEF@example.com'])->assertRedirect('/register/sent')->assertSessionHasNoErrors();

    expect(User::query()->count())->toBe(1);
    Notification::assertSentTo($owner, RegistrationAttemptNotification::class);
    Notification::assertNotSentTo($owner, VerifyEmailNotification::class);
    $this->assertGuest();
    expect(collect($this->securityEvents())->pluck('message'))->toContain('auth.registration_existing_email');
});

it('treats an address held by a deleted account as taken', function () {
    $owner = User::factory()->create(['email' => 'chief@example.com']);
    $owner->delete();

    $this->register(['email' => 'chief@example.com'])->assertRedirect('/register/sent');

    expect(User::withTrashed()->count())->toBe(1);
    Notification::assertSentTo($owner, RegistrationAttemptNotification::class);
});

it('answers non-string fields with field errors, not a crash', function () {
    $this->register(['email' => ['x'], 'username' => ['y'], 'password' => ['z']])
        ->assertRedirect('/register')
        ->assertSessionHasErrors(['email', 'username', 'password']);
});

it('sends the owner one notice an hour, however often their email is tried', function () {
    $owner = User::factory()->create(['email' => 'chief@example.com']);

    $this->register(['email' => 'chief@example.com', 'username' => 'first_try'])->assertRedirect('/register/sent');
    $this->register(['email' => 'chief@example.com', 'username' => 'second_try'])->assertRedirect('/register/sent');

    Notification::assertSentToTimes($owner, RegistrationAttemptNotification::class, 1);

    Date::setTestNow(now()->addHour()->addSecond());
    $this->register(['email' => 'chief@example.com', 'username' => 'third_try'])->assertRedirect('/register/sent');

    Notification::assertSentToTimes($owner, RegistrationAttemptNotification::class, 2);
});

it('takes the taken-email path when a concurrent sign-up wins the email', function () {
    // The rival commits right after our existence check, before our insert.
    $rival = null;
    DB::listen(function (QueryExecuted $query) use (&$rival) {
        if ($rival === null && str_starts_with($query->sql, 'select') && str_contains($query->sql, '"email"') && in_array('newcomer@example.com', $query->bindings, true)) {
            $rival = User::factory()->createQuietly(['email' => 'newcomer@example.com', 'username' => 'rival']);
        }
    });

    $this->register()->assertRedirect('/register/sent')->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'newcomer@example.com')->sole()->username)->toBe('rival');
    Notification::assertSentTo($rival, RegistrationAttemptNotification::class);
});

it('says the username is taken when a concurrent sign-up wins it', function () {
    $raced = false;
    User::creating(function (User $user) use (&$raced) {
        if (! $raced && $user->username === 'newcomer') {
            $raced = true;
            User::factory()->createQuietly(['email' => 'other@example.com', 'username' => 'newcomer']);
        }
    });

    $this->register()->assertRedirect('/register')->assertSessionHasErrors(['username' => 'That username is taken. Pick another.']);

    expect(User::query()->where('email', 'newcomer@example.com')->exists())->toBeFalse();
});

it('emails a confirmation link by ULID that expires, and a plain notice to an existing owner', function () {
    $user = User::factory()->unverified()->create(['username' => 'chief', 'email' => 'chief@example.com']);

    $mail = (new VerifyEmailNotification)->toMail($user);
    expect($mail->subject)->toBe('Confirm your Clash Commons email')
        ->and($mail->actionUrl)->toContain('/email/verify/'.$user->ulid.'/'.sha1('chief@example.com'))
        ->and($mail->actionUrl)->toContain('expires=')
        ->and($mail->actionUrl)->not->toContain('/'.$user->id.'/')
        ->and((new VerifyEmailNotification)->queue)->toBe('high');

    $notice = (new RegistrationAttemptNotification)->toMail($user);
    expect($notice->subject)->toBe('Someone tried to sign up with your email')
        ->and(implode(' ', $notice->introLines))->toContain('no new account was made');
});

it('reads its limits from config', function () {
    expect(config('platform.auth.register_per_hour'))->toBe(3)
        ->and(config('platform.auth.disposable_domains_min'))->toBe(1000)
        ->and(config('platform.auth.disposable_domains_timeout'))->toBe(30)
        ->and(config('services.turnstile.max_token_length'))->toBe(2048)
        ->and(config('platform.auth.register_attempts_per_hour'))->toBe(20)
        ->and(config('platform.auth.verify_resend_per_hour'))->toBe(3)
        ->and(config('platform.auth.register_min_seconds'))->toBe(3)
        ->and(config('platform.auth.register_max_form_age_minutes'))->toBe(120)
        ->and(config('platform.auth.verification_link_minutes'))->toBe(60)
        ->and(config('platform.auth.reserved_usernames'))->toContain('admin', 'mod', 'support', 'api', 'u', 'base');
});
