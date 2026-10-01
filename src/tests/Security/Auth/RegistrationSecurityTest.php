<?php

use App\Domain\Auth\Jobs\SendPasswordResetLinkJob;
use App\Domain\Auth\Services\RegistrationGuard;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Timebox;
use Illuminate\Testing\TestResponse;
use Tests\Support\Auth\CapturesSecurityLog;
use Tests\Support\Auth\FakesHibp;
use Tests\Support\Auth\RegistersAccounts;

// specs/11 "Account enumeration": registering with a taken email looks exactly like a new one,
// in the answer, the cookies and the time taken. "Spam and fake accounts": bot traps, the limits,
// mass assignment. Turnstile guards the reset-link form too.

uses(CapturesSecurityLog::class, FakesHibp::class, RegistersAccounts::class);

beforeEach(function () {
    $this->fakeHibp();
    $this->captureSecurityLog();
    Notification::fake();
    Date::setTestNow('2026-10-01 12:00:00');
});

/**
 * @return array{status: int, location: ?string, cookies: list<string>, flashed: list<string>}
 */
function observable(TestResponse $response): array
{
    $cookies = array_map(fn ($cookie) => $cookie->getName(), $response->headers->getCookies());
    sort($cookies);

    return [
        'status' => $response->getStatusCode(),
        'location' => $response->headers->get('Location'),
        'cookies' => $cookies,
        'flashed' => array_values(array_diff(array_keys(session()->all()), ['_token', '_previous', 'url', 'register.nonces'])),
    ];
}

function spyTimebox(): array
{
    $calls = new ArrayObject;
    app()->instance(Timebox::class, new class($calls) extends Timebox
    {
        public function __construct(private ArrayObject $calls) {}

        public function call(callable $callback, int $microseconds)
        {
            $this->calls[] = $microseconds;

            return $callback($this);
        }
    });

    return ['calls' => $calls];
}

it('answers a new and a taken email identically', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $new = observable($this->register(['email' => 'fresh@example.com', 'username' => 'freshone']));
    $this->flushSession();
    $taken = observable($this->register(['email' => 'taken@example.com', 'username' => 'anotherone']));

    expect($taken)->toBe($new)
        ->and($new['location'])->toEndWith('/register/sent');
});

it('runs every path inside the configured timebox, a trapped bot included', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $spy = spyTimebox();

    $this->register(['email' => 'fresh@example.com', 'username' => 'freshone']);
    $this->register(['email' => 'taken@example.com', 'username' => 'anotherone']);
    $this->register(['email' => 'bot@example.com', 'username' => 'botone', 'website' => 'spam']);

    expect($spy['calls']->getArrayCopy())->toBe(array_fill(0, 3, (int) config('auth.timebox_duration')));
});

it('never signs anyone in, whatever the email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->register(['email' => 'fresh@example.com', 'username' => 'freshone']);
    $this->assertGuest();
    $this->register(['email' => 'taken@example.com', 'username' => 'anotherone']);
    $this->assertGuest();
});

it('keeps the existing account untouched when its email is used again', function () {
    $owner = User::factory()->create(['email' => 'taken@example.com', 'username' => 'owner']);
    $password = $owner->password;

    $this->register(['email' => 'taken@example.com', 'username' => 'intruder']);

    expect($owner->refresh()->username)->toBe('owner')
        ->and($owner->password)->toBe($password)
        ->and(User::query()->where('username', 'intruder')->exists())->toBeFalse();
});

it('silently drops a trapped submission and logs why', function (array $input, string $reason) {
    $this->register($input)->assertRedirect('/register/sent')->assertSessionHasNoErrors();

    expect(User::query()->count())->toBe(0);
    Notification::assertNothingSent();
    $line = collect($this->securityEvents())->firstWhere('message', 'auth.registration_blocked');
    expect($line['context']['reason'])->toBe($reason)
        ->and($line['context'])->toHaveKey('ip_hash');
})->with([
    'honeypot filled' => [['website' => 'https://spam.example'], 'honeypot'],
    'no form token' => [['started' => ''], 'no_form_time'],
    'forged form token' => [['started' => 'not-encrypted'], 'bad_form_time'],
]);

it('drops a form sent faster than a person could', function () {
    $fast = RegistrationGuard::startToken();
    $this->from('/register')->post('/register', [...$this->registrationInput(), 'started' => $fast])->assertRedirect('/register/sent');

    expect(User::query()->count())->toBe(0)
        ->and(collect($this->securityEvents())->firstWhere('message', 'auth.registration_blocked')['context']['reason'])->toBe('too_fast');
});

it('accepts each form token once, so a replayed token is dropped', function () {
    $input = $this->registrationInput();

    $this->from('/register')->post('/register', $input)->assertRedirect('/register/sent');
    $this->from('/register')->post('/register', [...$input, 'email' => 'second@example.com', 'username' => 'secondone'])->assertRedirect('/register/sent');

    expect(User::query()->pluck('username')->all())->toBe(['newcomer'])
        ->and(collect($this->securityEvents())->firstWhere('message', 'auth.registration_blocked')['context']['reason'])->toBe('replayed_form');
});

it('asks a person to reload a form kept open too long, without counting it', function () {
    $old = $this->registrationInput();
    Date::setTestNow(now()->addMinutes((int) config('platform.auth.register_max_form_age_minutes'))->addSecond());

    $this->from('/register')->post('/register', $old)
        ->assertRedirect('/register')
        ->assertSessionHasErrors(['email' => 'This form was open for a long time. Reload the page and try again.']);

    expect(User::query()->count())->toBe(0);
});

it('ignores role, status and verification fields in the form', function () {
    $this->register(['role' => 'super_admin', 'status' => 'active', 'email_verified_at' => now()->toDateTimeString()]);

    $user = User::query()->where('email', 'newcomer@example.com')->sole();
    expect($user->role->value)->toBe('user')
        ->and($user->email_verified_at)->toBeNull();
});

it('limits accepted sign-ups per IP, as a field error, without counting typos', function () {
    $limit = (int) config('platform.auth.register_per_hour');

    foreach (range(1, $limit + 2) as $i) {
        $this->register(['username' => 'ab'])->assertSessionHasErrors('username');
    }
    foreach (range(1, $limit) as $i) {
        $this->register(['email' => "n{$i}@example.com", 'username' => "newcomer{$i}"])->assertRedirect('/register/sent');
    }

    $this->register(['email' => 'over@example.com', 'username' => 'overlimit'])
        ->assertRedirect('/register')
        ->assertSessionHasErrors(['email' => 'Too many attempts. Try again in 60 minutes.']);
    expect(User::query()->where('email', 'over@example.com')->exists())->toBeFalse();
});

it('caps every attempt per IP on the route', function () {
    config(['platform.auth.register_attempts_per_hour' => 2]);

    $this->register(['username' => 'ab']);
    $this->register(['username' => 'ab']);

    $this->register()->assertSessionHasErrors(['email' => 'Too many attempts. Try again in 60 minutes.']);
    expect(User::query()->count())->toBe(0);
});

it('refuses the reset-link form when Turnstile fails, and sends nothing', function () {
    Bus::fake();
    $this->turnstile(pass: false);

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'chief@example.com', 'turnstile_token' => 'bad'])
        ->assertRedirect('/forgot-password')
        ->assertSessionHasErrors(['turnstile_token' => 'We could not confirm you are not a bot. Try again.']);

    Bus::assertNotDispatched(SendPasswordResetLinkJob::class);
});

it('passes the reset-link form with Turnstile, and gives the Turnstile site key to the page', function () {
    Bus::fake();
    $turnstile = $this->turnstile();

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'chief@example.com', 'turnstile_token' => 'good'])
        ->assertRedirect('/forgot-password')
        ->assertSessionHasNoErrors();

    expect($turnstile->tokens)->toBe(['good']);
    Bus::assertDispatched(SendPasswordResetLinkJob::class);
    $this->get('/forgot-password')->assertInertia(fn ($page) => $page->where('turnstileSiteKey', config('services.turnstile.site_key')));
});
