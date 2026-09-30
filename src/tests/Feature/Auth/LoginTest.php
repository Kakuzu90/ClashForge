<?php

use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function member(array $attributes = []): User
{
    return User::factory()->create(['email' => 'chief@example.com', 'password' => 'a-long-password', ...$attributes]);
}

it('renders the sign-in page for guests', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('status', null)->where('meta.title', 'Sign in'));
});

it('keeps every sign-in and reset route for guests only', function (string $method, string $uri) {
    $this->actingAs(User::factory()->create())->call($method, $uri, ['email' => 'chief@example.com'])->assertRedirect('/');
})->with([
    'GET /login' => ['GET', '/login'],
    'POST /login' => ['POST', '/login'],
    'GET /forgot-password' => ['GET', '/forgot-password'],
    'POST /forgot-password' => ['POST', '/forgot-password'],
    'GET /reset-password/{token}' => ['GET', '/reset-password/some-token'],
    'POST /reset-password' => ['POST', '/reset-password'],
]);

it('signs in with email and password and returns to the page that asked for it', function () {
    Route::get('/_test/private', fn () => 'private')->middleware(['web', 'auth']);
    $user = member();

    $this->get('/_test/private')->assertRedirect('/login');
    $this->post('/login', ['email' => 'chief@example.com', 'password' => 'a-long-password'])->assertRedirect('/_test/private');

    $this->assertAuthenticatedAs($user);
});

it('treats the email case-insensitively', function () {
    $user = member();

    $this->post('/login', ['email' => 'Chief@Example.COM', 'password' => 'a-long-password'])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('records the login time and a hash of the IP, never the IP', function () {
    $user = member();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->post('/login', ['email' => 'chief@example.com', 'password' => 'a-long-password']);

    $user->refresh();
    expect($user->last_login_at)->not->toBeNull()
        ->and($user->last_login_ip_hash)->toBe(IpHash::of('203.0.113.9'))
        ->and($user->last_login_ip_hash)->not->toContain('203.0.113.9');
});

it('sets the remember cookie only when asked', function () {
    member();

    $this->post('/login', ['email' => 'chief@example.com', 'password' => 'a-long-password'])
        ->assertCookieMissing(Auth::guard()->getRecallerName());

    Auth::logout();

    $response = $this->post('/login', ['email' => 'chief@example.com', 'password' => 'a-long-password', 'remember' => '1'])
        ->assertCookie(Auth::guard()->getRecallerName());

    // 30 days, the absolute cap (specs/04 §4), not the framework's 400.
    $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === Auth::guard()->getRecallerName());
    expect($cookie->getExpiresTime())->toBeLessThanOrEqual(now()->addDays(30)->addMinute()->getTimestamp())
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->getTimestamp());
});

it('rehashes a password stored at an older cost', function () {
    $user = member(['password' => Hash::make('a-long-password', ['rounds' => 4])]);
    config(['hashing.bcrypt.rounds' => 5]);
    Hash::forgetDrivers();

    $this->post('/login', ['email' => 'chief@example.com', 'password' => 'a-long-password']);

    expect(password_get_info($user->refresh()->password)['options']['cost'])->toBe(5);
});

it('refuses deleted accounts', function () {
    member()->delete();

    $this->post('/login', ['email' => 'chief@example.com', 'password' => 'a-long-password'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('signs out, ends the session and cycles the remember token', function () {
    $user = member(['remember_token' => 'old-token']);
    $this->actingAs($user)->get('/');
    $before = session()->getId();

    $this->post('/logout')->assertRedirect('/');

    $this->assertGuest();
    expect(session()->getId())->not->toBe($before);
    expect($user->refresh()->remember_token)->not->toBe('old-token');
});

it('only lets signed-in users sign out', function () {
    $this->post('/logout')->assertRedirect('/login');
});

it('shares the signed-in user without private fields', function () {
    $user = member(['username' => 'night_owl']);

    $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.username', 'night_owl')
        ->where('auth.user.emailVerified', true)
        ->missing('auth.user.email')
        ->missing('auth.user.id')
        ->missing('auth.user.last_login_ip_hash'));
});

it('asks for a valid email before checking any account', function (string $email) {
    Hash::shouldReceive('check')->never();

    $this->from('/login')->post('/login', ['email' => $email, 'password' => 'a-long-password'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Enter a valid email address.']);

    $this->assertGuest();
})->with(['chief', 'chief@', '@example.com', 'chief example.com']);
