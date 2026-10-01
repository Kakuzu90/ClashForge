<?php

use App\Domain\Auth\Events\UnrecognisedDeviceSignedIn;
use App\Domain\Auth\Notifications\NewSignInNotification;
use App\Domain\Auth\Notifications\PasswordChangedNotification;
use App\Domain\Auth\Services\SessionService;
use App\Domain\Auth\Support\KnownDevices;
use App\Domain\Auth\Support\RememberOrigin;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Auth\CapturesSecurityLog;
use Tests\Support\Auth\FakesHibp;
use Tests\Support\Auth\InteractsWithBrowsers;

// FR-AUTH-7 and the specs/04 §4 session rules at /settings/security: password change, the session
// list, revocation, the 30-day absolute cap, new-device emails and the 15-minute confirm page.

uses(CapturesSecurityLog::class, FakesHibp::class, InteractsWithBrowsers::class);

const FIREFOX = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0';
const SAFARI_IOS = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

beforeEach(function () {
    // The CDN country header is believed only through a trusted proxy.
    TrustProxies::at('*');
    $this->useDatabaseSessions();
    $this->fakeHibp(['password123456']);
    $this->captureSecurityLog();
    Notification::fake();
    // Signed in before, so a new browser is a new device (the very first sign-in is not).
    $this->user = User::factory()->create(['email' => 'chief@example.com', 'password' => 'password', 'last_login_at' => now()->subDay()]);
});

afterEach(fn () => TrustProxies::flushState());

function sessionRows(User $user): array
{
    return DB::table('sessions')->where('user_id', $user->id)->get()->all();
}

it('stores a hashed IP, a device label, the country and the creation time, never the raw IP', function () {
    expect(Schema::hasColumn('sessions', 'ip_address'))->toBeFalse();

    $this->signInBrowser($this->user, ['User-Agent' => FIREFOX, 'CF-IPCountry' => 'de']);

    $row = sessionRows($this->user)[0];
    expect($row->device_label)->toBe('Firefox on Windows')
        ->and($row->country_code)->toBe('DE')
        ->and($row->ip_hash)->toBe(IpHash::of('127.0.0.1'))
        ->and($row->created_at)->not->toBeNull();
});

it('ignores country codes the CDN uses for unknown and Tor', function (string $header) {
    $this->signInBrowser($this->user, ['CF-IPCountry' => $header]);

    expect(sessionRows($this->user)[0]->country_code)->toBeNull();
})->with(['XX', 'T1', 'not-a-country']);

it('ignores the country header on requests that did not come through a trusted proxy', function () {
    TrustProxies::flushState();

    $this->signInBrowser($this->user, ['CF-IPCountry' => 'DE']);

    expect(sessionRows($this->user)[0]->country_code)->toBeNull();
    Notification::assertSentTo($this->user, NewSignInNotification::class, fn (NewSignInNotification $n) => $n->country === null);
});

it('sends a guest to sign in', function () {
    $this->get('/settings/security')->assertRedirect('/login');
    $this->put('/settings/security/password', [])->assertRedirect('/login');
    $this->delete('/settings/security/sessions')->assertRedirect('/login');
});

it('renders the page and loads the session list as a deferred prop, this browser first', function () {
    $phone = $this->signInBrowser($this->user, ['User-Agent' => SAFARI_IOS]);
    $laptop = $this->signInBrowser($this->user, ['User-Agent' => FIREFOX, 'CF-IPCountry' => 'DE']);

    $this->browser($laptop, ['User-Agent' => FIREFOX, 'CF-IPCountry' => 'DE'])->get('/settings/security')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Security')
            ->where('passwordMinLength', config('platform.auth.min_password_length'))
            ->where('meta.title', 'Security settings')
            ->missing('sessions'));

    $sessions = $this->loadDeferred($laptop, '/settings/security', 'Settings/Security', 'sessions')->assertOk()->json('props.sessions');

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0])->toMatchArray(['isCurrent' => true, 'deviceLabel' => 'Firefox on Windows', 'country' => 'Germany'])
        ->and($sessions[1])->toMatchArray(['isCurrent' => false, 'deviceLabel' => 'Safari on iOS', 'country' => null])
        ->and(array_keys($sessions[0]))->toBe(['key', 'deviceLabel', 'country', 'lastActiveAt', 'signedInAt', 'isCurrent'])
        ->and(collect(sessionRows($this->user))->pluck('id')->all())->not->toContain($sessions[0]['key'], $sessions[1]['key']);
    expect($phone)->not->toBeEmpty();
});

it('signs out one other browser by its key, and its remember cookie stops working', function () {
    $phone = $this->signInBrowser($this->user, ['User-Agent' => SAFARI_IOS], remember: true);
    $laptop = $this->signInBrowser($this->user, ['User-Agent' => FIREFOX], remember: true);
    $phoneRow = collect(sessionRows($this->user))->firstWhere('device_label', 'Safari on iOS');

    $response = $this->browser($laptop, ['User-Agent' => FIREFOX])->from('/settings/security')
        ->delete('/settings/security/sessions/'.SessionService::keyOf($phoneRow->id))
        ->assertRedirect('/settings/security')
        ->assertSessionHas('success');

    expect(DB::table('sessions')->where('id', $phoneRow->id)->exists())->toBeFalse();

    // The phone is signed out, even holding only its remember cookie.
    $this->browser($phone)->get('/settings/security')->assertRedirect('/login');
    $recaller = collect($phone)->filter(fn ($v, $name) => str_starts_with($name, 'remember_web_'))->all();
    $this->browser($recaller)->get('/settings/security')->assertRedirect('/login');

    // This laptop stays signed in for its session; its own remember cookie is cleared, not
    // re-issued, so the 30 days from the password sign-in still hold.
    $cleared = collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
    expect($cleared?->isCleared() || $cleared?->getExpiresTime() < time())->toBeTrue();
    $this->browser(collect($laptop)->reject(fn ($v, $name) => str_starts_with($name, 'remember_web_'))->all())->get('/settings/security')->assertOk();
});

it('leaves idle-expired rows out of the list and the count', function () {
    $current = $this->signInBrowser($this->user);
    DB::table('sessions')->insert([
        'id' => str_repeat('z', 40),
        'user_id' => $this->user->id,
        'payload' => base64_encode('a:0:{}'),
        'last_activity' => now()->subMinutes((int) config('session.lifetime') + 1)->getTimestamp(),
        'device_label' => 'Old laptop',
    ]);

    $sessions = $this->loadDeferred($current, '/settings/security', 'Settings/Security', 'sessions')->json('props.sessions');
    expect($sessions)->toHaveCount(1);

    $this->browser($current)->delete('/settings/security/sessions/'.SessionService::keyOf(str_repeat('z', 40)))->assertNotFound();
    $this->browser($current)->delete('/settings/security/sessions')->assertSessionHas('success', 'No other devices were signed in.');
    expect(DB::table('sessions')->where('id', str_repeat('z', 40))->exists())->toBeFalse();
});

it('signs out every other browser and keeps this one', function () {
    $this->signInBrowser($this->user, ['User-Agent' => SAFARI_IOS]);
    $this->signInBrowser($this->user, ['User-Agent' => FIREFOX]);
    $current = $this->signInBrowser($this->user);
    expect(sessionRows($this->user))->toHaveCount(3);

    $response = $this->browser($current)->delete('/settings/security/sessions')->assertSessionHas('success', 'Every other device was signed out.');

    expect(sessionRows($this->user))->toHaveCount(1);
    $this->browser([...$current, ...self::cookiesFrom($response)])->get('/settings/security')->assertOk();
    expect(collect($this->securityEvents())->firstWhere('message', 'auth.session_revoked')['context']['count'])->toBe(2);
});

it('answers a key that is not one of the account\'s other sessions with a 404', function () {
    $current = $this->signInBrowser($this->user);
    $otherUser = User::factory()->create(['password' => 'password']);
    $this->signInBrowser($otherUser);
    $own = sessionRows($this->user)[0];
    $foreign = sessionRows($otherUser)[0];

    $this->browser($current)->delete('/settings/security/sessions/'.SessionService::keyOf($own->id))->assertNotFound();
    $this->browser($current)->delete('/settings/security/sessions/'.SessionService::keyOf($foreign->id))->assertNotFound();
    $this->browser($current)->delete('/settings/security/sessions/'.str_repeat('a', 32))->assertNotFound();

    expect(DB::table('sessions')->where('id', $foreign->id)->exists())->toBeTrue();
});

it('changes the password, ends every other session and emails the owner', function () {
    $phone = $this->signInBrowser($this->user, ['User-Agent' => SAFARI_IOS]);
    $current = $this->signInBrowser($this->user);

    $this->browser($current)->from('/settings/security')->put('/settings/security/password', [
        'current_password' => 'password',
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'a-brand-new-passphrase',
    ])->assertRedirect('/settings/security')->assertSessionHasNoErrors();

    expect(Hash::check('a-brand-new-passphrase', $this->user->refresh()->password))->toBeTrue()
        ->and(sessionRows($this->user))->toHaveCount(1);
    $this->browser($phone)->get('/settings/security')->assertRedirect('/login');

    Notification::assertSentTo($this->user, PasswordChangedNotification::class);
    expect(collect($this->securityEvents())->pluck('message'))->toContain('auth.password_changed');
});

it('gives this browser a new session id on a password change or a sign-out everywhere', function (string $method, string $url, array $body) {
    $current = $this->signInBrowser($this->user);
    $oldId = sessionRows($this->user)[0]->id;

    $response = $this->browser($current)->from('/settings/security')->{strtolower($method)}($url, $body);
    $response->assertRedirect('/settings/security');

    // A copy of the old cookie is signed out; the browser's new cookie is signed in.
    expect(DB::table('sessions')->where('id', $oldId)->exists())->toBeFalse();
    $this->browser($current)->get('/settings/security')->assertRedirect('/login');
    $this->browser([...$current, ...self::cookiesFrom($response)])->get('/settings/security')->assertOk();
})->with([
    'password change' => ['PUT', '/settings/security/password', ['current_password' => 'password', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase']],
    'sign out everywhere' => ['DELETE', '/settings/security/sessions', []],
]);

it('counts the password change as a password confirmation', function () {
    Route::middleware(['web', 'auth', 'password.confirm'])->get('/confirm-probe', fn () => 'confirmed');
    $current = $this->signInBrowser($this->user);

    $response = $this->browser($current)->put('/settings/security/password', [
        'current_password' => 'password',
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'a-brand-new-passphrase',
    ]);

    $this->browser([...$current, ...self::cookiesFrom($response)])->get('/confirm-probe')->assertOk()->assertSee('confirmed');
});

it('rejects a wrong current password and logs it', function () {
    $this->actingAs($this->user)->from('/settings/security')->put('/settings/security/password', [
        'current_password' => 'not-my-password',
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'a-brand-new-passphrase',
    ])->assertSessionHasErrors(['current_password' => 'That is not your current password.']);

    expect(Hash::check('password', $this->user->refresh()->password))->toBeTrue()
        ->and(collect($this->securityEvents())->pluck('message'))->toContain('auth.password_change_failed');
    Notification::assertNothingSentTo($this->user, PasswordChangedNotification::class);
});

it('validates the new password', function (array $input, string $field) {
    $this->actingAs($this->user)->from('/settings/security')->put('/settings/security/password', [
        'current_password' => 'password',
        'password' => 'a-brand-new-passphrase',
        'password_confirmation' => 'a-brand-new-passphrase',
        ...$input,
    ])->assertSessionHasErrors($field);
})->with([
    'too short' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'not repeated' => [['password_confirmation' => 'something-else-entirely'], 'password'],
    'same as current' => [['password' => 'password', 'password_confirmation' => 'password'], 'password'],
    'breached' => [['password' => 'password123456', 'password_confirmation' => 'password123456'], 'password'],
    'missing current' => [['current_password' => ''], 'current_password'],
]);

it('limits current-password guesses on the password form', function () {
    // Frozen, so the wait in the message cannot tick from 60 to 59 between requests.
    $this->freezeTime();
    $limit = (int) config('platform.auth.password_confirm_per_minute');
    $payload = ['current_password' => 'wrong-guess', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase'];

    for ($i = 0; $i < $limit; $i++) {
        $this->actingAs($this->user)->from('/settings/security')->put('/settings/security/password', $payload);
    }

    $this->actingAs($this->user)->from('/settings/security')->put('/settings/security/password', $payload)
        ->assertSessionHasErrors(['current_password' => 'Too many attempts. Try again in 60 seconds.']);
});

it('signs a session out 30 days after sign-in, however active it is', function () {
    $browser = $this->signInBrowser($this->user);
    $days = (int) config('platform.auth.absolute_session_days');

    // Keep it active: one request every 10 days stays inside the 14-day idle limit.
    for ($elapsed = 10; $elapsed < $days; $elapsed += 10) {
        $this->travel(10)->days();
        $this->browser($browser)->get('/settings/security')->assertOk();
    }

    $this->travel($days - ($elapsed - 10))->days();
    $this->browser($browser)->get('/settings/security')
        ->assertRedirect('/login')
        ->assertSessionHas('status', __('auth.session_expired'));

    expect(collect($this->securityEvents())->pluck('message'))->toContain('auth.session_expired');
});

it('answers an expired Inertia write with a 303 and a JSON call with a 401', function () {
    $signedIn = now()->subDays((int) config('platform.auth.absolute_session_days'))->getTimestamp();

    $this->actingAs($this->user)->withSession([SessionService::SIGNED_IN_AT => $signedIn])
        ->withHeaders(['X-Inertia' => 'true'])
        ->delete('/settings/security/sessions')
        ->assertStatus(303)
        ->assertRedirect('/login');

    $this->freshRequestState();
    $this->actingAs($this->user)->withSession([SessionService::SIGNED_IN_AT => $signedIn])
        ->getJson('/settings/security')
        ->assertStatus(401);
});

it('limits password guesses on the confirm page, sharing the password form\'s bucket', function () {
    // Frozen, so the wait in the message cannot tick from 60 to 59 between requests.
    $this->freezeTime();
    $limit = (int) config('platform.auth.password_confirm_per_minute');

    for ($i = 0; $i < $limit; $i++) {
        $this->actingAs($this->user)->from('/confirm-password')->post('/confirm-password', ['password' => 'wrong']);
    }

    $this->actingAs($this->user)->from('/confirm-password')->post('/confirm-password', ['password' => 'password'])
        ->assertSessionHasErrors(['password' => 'Too many attempts. Try again in 60 seconds.']);
    $this->actingAs($this->user)->from('/settings/security')->put('/settings/security/password', [
        'current_password' => 'password', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase',
    ])->assertSessionHasErrors('current_password');
});

it('lets a remember-me sign-in inherit the password sign-in time, so it never outlasts 30 days', function () {
    $days = (int) config('platform.auth.absolute_session_days');
    $browser = $this->signInBrowser($this->user, remember: true);
    $remembered = collect($browser)->filter(fn ($v, $name) => str_starts_with($name, 'remember_web_') || $name === RememberOrigin::COOKIE)->all();
    expect($remembered)->toHaveKey(RememberOrigin::COOKIE);

    // Day 20: the session cookie is gone, the remember cookie signs the browser back in.
    $this->travel(20)->days();
    $this->browser($remembered)->get('/settings/security')->assertOk();

    // Day 30 from the password sign-in: a fresh remember-me session is still past the cap.
    $this->travel($days - 20)->days();
    $this->browser($remembered)->get('/settings/security')->assertRedirect('/login');
});

it('refuses a remember-me sign-in that cannot show when the password sign-in happened', function () {
    $browser = $this->signInBrowser($this->user, remember: true);
    $recallerOnly = collect($browser)->filter(fn ($v, $name) => str_starts_with($name, 'remember_web_'))->all();

    $this->browser($recallerOnly)->get('/settings/security')->assertRedirect('/login');
});

it('limits current-password guesses per hour too', function () {
    $perMinute = (int) config('platform.auth.password_confirm_per_minute');
    $perHour = (int) config('platform.auth.password_confirm_per_hour');
    $payload = ['current_password' => 'wrong-guess', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase'];

    for ($i = 0; $i < $perHour; $i++) {
        if ($i > 0 && $i % $perMinute === 0) {
            $this->travel(61)->seconds();
        }
        $this->actingAs($this->user)->from('/settings/security')->put('/settings/security/password', $payload);
    }

    $this->travel(61)->seconds();
    $this->actingAs($this->user)->from('/settings/security')->put('/settings/security/password', [...$payload, 'current_password' => 'password'])
        ->assertSessionHasErrors('current_password');
    expect(session('errors')->first('current_password'))->toStartWith('Too many attempts.');
    expect(Hash::check('password', $this->user->refresh()->password))->toBeTrue();
});

it('starts the clock for a session from before the clock existed', function () {
    $this->actingAs($this->user)->get('/settings/security')->assertOk()->assertSessionHas(SessionService::SIGNED_IN_AT);
});

it('sends a first-sign-in email, not a new-device one, on an account\'s very first sign-in', function () {
    Event::fake([UnrecognisedDeviceSignedIn::class]);
    $newcomer = User::factory()->create(['password' => 'password']);

    $browser = $this->signInBrowser($newcomer, ['User-Agent' => FIREFOX]);

    Notification::assertSentTo($newcomer, NewSignInNotification::class, fn (NewSignInNotification $n) => $n->firstSignIn
        && $n->toMail($newcomer)->subject === 'First sign-in to your Clash Commons account');
    Event::assertNotDispatched(UnrecognisedDeviceSignedIn::class);
    expect($browser)->toHaveKey(KnownDevices::COOKIE)
        ->and($newcomer->refresh()->last_login_at)->not->toBeNull();
});

it('emails the owner about a sign-in from a browser it has not seen, once', function () {
    $first = $this->signInBrowser($this->user, ['User-Agent' => FIREFOX, 'CF-IPCountry' => 'DE']);

    Notification::assertSentTo($this->user, NewSignInNotification::class, fn (NewSignInNotification $n) => $n->deviceLabel === 'Firefox on Windows' && $n->country === 'Germany');
    expect($first)->toHaveKey(KnownDevices::COOKIE);

    Notification::fake();
    $this->signInBrowser($this->user, ['User-Agent' => FIREFOX], cookies: [KnownDevices::COOKIE => $first[KnownDevices::COOKIE]]);
    Notification::assertNothingSent();

    // Another account on the same browser is new to that account.
    $partner = User::factory()->create(['password' => 'password', 'last_login_at' => now()->subDay()]);
    $this->signInBrowser($partner, cookies: [KnownDevices::COOKIE => $first[KnownDevices::COOKIE]]);
    Notification::assertSentTo($partner, NewSignInNotification::class);
});

it('remembers only the most recent accounts on one browser', function () {
    $max = (int) config('platform.auth.known_devices_max');
    $request = Request::create('/');
    $request->cookies->set(KnownDevices::COOKIE, (string) json_encode(array_map(fn (int $i) => "account-{$i}", range(1, $max))));

    KnownDevices::remember($request, 'account-1');
    $kept = json_decode(Cookie::queued(KnownDevices::COOKIE)->getValue(), true);

    expect($kept)->toHaveCount($max)
        ->and(end($kept))->toBe('account-1')
        ->and($kept[0])->toBe('account-2');

    $request->cookies->set(KnownDevices::COOKIE, (string) json_encode($kept));
    KnownDevices::remember($request, 'account-new');
    $kept = json_decode(Cookie::queued(KnownDevices::COOKIE)->getValue(), true);

    expect($kept)->toHaveCount($max)->not->toContain('account-2')->and(end($kept))->toBe('account-new');
});

it('asks for the password before a confirm-protected action, for 15 minutes', function () {
    Route::middleware(['web', 'auth', 'password.confirm'])->get('/confirm-probe', fn () => 'confirmed');
    $browser = $this->signInBrowser($this->user);

    $this->browser($browser)->get('/confirm-probe')->assertRedirect('/confirm-password');
    $this->browser($browser)->get('/confirm-password')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Auth/ConfirmPassword')
        ->where('minutes', 15)
        ->where('meta.title', 'Confirm your password'));

    $this->browser($browser)->from('/confirm-password')->post('/confirm-password', ['password' => 'wrong'])
        ->assertSessionHasErrors(['password' => 'That is not your password.']);
    expect(collect($this->securityEvents())->pluck('message'))->toContain('auth.password_confirm_failed');

    $this->browser($browser)->post('/confirm-password', ['password' => 'password'])->assertRedirect('/confirm-probe');
    $this->browser($browser)->get('/confirm-probe')->assertOk();

    $this->travel(16)->minutes();
    $this->browser($browser)->get('/confirm-probe')->assertRedirect('/confirm-password');
});

it('keeps the session and confirmation limits in config', function () {
    expect(config('auth.password_timeout'))->toBe(900)
        ->and(config('platform.auth.absolute_session_days'))->toBe(30)
        ->and(config('platform.auth.known_device_days'))->toBe(365)
        ->and(config('platform.auth.known_devices_max'))->toBe(10)
        ->and(config('platform.auth.password_confirm_per_minute'))->toBe(5)
        ->and(config('platform.auth.password_confirm_per_hour'))->toBe(20)
        ->and(config('platform.auth.country_header'))->toBe('CF-IPCountry');
});
