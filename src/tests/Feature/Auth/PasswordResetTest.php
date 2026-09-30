<?php

use App\Domain\Auth\Jobs\SendPasswordResetLinkJob;
use App\Domain\Auth\Notifications\ResetPasswordNotification;
use App\Http\Responses\Auth\PasswordResetDoneResponse;
use App\Http\Responses\Auth\PasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Auth\FakesHibp;

uses(FakesHibp::class);

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'chief@example.com', 'remember_token' => 'old-token']);
});

function resetPayload(string $token, array $overrides = []): array
{
    return ['token' => $token, 'email' => 'chief@example.com', 'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password', ...$overrides];
}

it('renders the request and reset pages', function () {
    $this->fakeHibp();
    $this->get('/forgot-password')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword')->where('status', null));

    $this->get('/reset-password/some-token?email=chief@example.com')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Auth/ResetPassword')
        ->where('token', 'some-token')
        ->where('email', 'chief@example.com'));
});

it('emails a reset link on the high queue', function () {
    Notification::fake();

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'chief@example.com'])
        ->assertRedirect('/forgot-password')
        ->assertSessionHas('status', PasswordResetLinkResponse::message());

    Notification::assertSentTo($this->user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification): bool {
        $mail = $notification->toMail($this->user);

        return $notification->queue === 'high'
            && str_contains((string) $mail->actionUrl, '/reset-password/')
            && $mail->greeting === "Hi {$this->user->username},";
    });
});

it('answers at once and leaves the lookup and email to a queued job', function (string $email) {
    Queue::fake();

    $this->from('/forgot-password')->post('/forgot-password', ['email' => $email])
        ->assertRedirect('/forgot-password')
        ->assertSessionHas('status', PasswordResetLinkResponse::message());

    Queue::assertPushedOn('high', SendPasswordResetLinkJob::class, fn (SendPasswordResetLinkJob $job) => $job->email === strtolower(trim($email)));
    // Nothing account-specific happens in the request, so its timing says nothing.
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
})->with([
    'known account' => ['chief@example.com'],
    'unknown email' => ['nobody@example.com'],
    'known, other case and spaces' => ['  Chief@Example.com '],
]);

it('validates the email before queueing anything', function () {
    Queue::fake();

    $this->post('/forgot-password', ['email' => 'not-an-email'])->assertSessionHasErrors('email');

    Queue::assertNothingPushed();
});

it('sets the new password, ends every session and consumes the token', function () {
    $this->fakeHibp();
    DB::table('sessions')->insert([
        ['id' => 'phone', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'laptop', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'someone-else', 'user_id' => null, 'payload' => '', 'last_activity' => time()],
    ]);
    $token = Password::createToken($this->user);

    $this->post('/reset-password', resetPayload($token))
        ->assertRedirect('/login')
        ->assertSessionHas('status', PasswordResetDoneResponse::MESSAGE);

    $this->user->refresh();
    expect(Hash::check('brand-new-password', $this->user->password))->toBeTrue()
        ->and($this->user->remember_token)->not->toBe('old-token')
        ->and(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'someone-else')->exists())->toBeTrue()
        ->and(DB::table('password_reset_tokens')->where('email', 'chief@example.com')->exists())->toBeFalse();
});

it('keeps the token when the new password is rejected', function (array $overrides, string $field) {
    $this->fakeHibp();
    $token = Password::createToken($this->user);

    $this->post('/reset-password', resetPayload($token, $overrides))->assertSessionHasErrors($field);

    expect(DB::table('password_reset_tokens')->where('email', 'chief@example.com')->exists())->toBeTrue();
})->with([
    'shorter than the minimum' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'confirmation differs' => [['password_confirmation' => 'something-else-entirely'], 'password'],
]);

it('rejects a password found in a breach', function () {
    $this->fakeHibp(['brand-new-password']);
    $token = Password::createToken($this->user);

    $this->post('/reset-password', resetPayload($token))->assertSessionHasErrors([
        'password' => 'This password appears in a known data breach. Choose a different one.',
    ]);
});

it('accepts the password and logs it when the breach check cannot be reached', function () {
    Log::spy();
    Log::shouldReceive('channel')->andReturnSelf();
    $this->hibpDown();
    $token = Password::createToken($this->user);

    $this->post('/reset-password', resetPayload($token))->assertRedirect('/login');

    Log::shouldHaveReceived('warning')->with('auth.hibp_unavailable', ['status' => 503])->once();
});

it('reads its limits from config', function () {
    $this->fakeHibp();
    config(['platform.auth.min_password_length' => 20]);
    $token = Password::createToken($this->user);

    $this->post('/reset-password', resetPayload($token))->assertSessionHasErrors('password');
});
