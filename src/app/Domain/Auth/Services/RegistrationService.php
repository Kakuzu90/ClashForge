<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Data\RegistrationData;
use App\Domain\Auth\Events\UserRegistered;
use App\Domain\Auth\Notifications\RegistrationAttemptNotification;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

/**
 * Registration (FR-AUTH-1/3, specs/04 §4). A new email gets an account and a verification link; an
 * email that already has an account gets a notice instead (specs/23 §1), at most once an hour per
 * account. Every path, a trapped bot included, hashes the password inside one timebox, and none
 * signs anyone in, so the answer, cookies and timing say nothing about which happened (specs/11
 * "Account enumeration").
 */
class RegistrationService
{
    private const NOTICE_WINDOW = 3600;

    public function __construct(
        private readonly Timebox $timebox,
        private readonly RegistrationGuard $guard,
        private readonly RegistrationLimit $limit,
    ) {}

    /**
     * A validated submission: the accepted-sign-up limit, an expired form, then the bot traps and
     * the registration itself inside the timebox.
     *
     * @throws ValidationException
     */
    public function submit(RegistrationData $data, mixed $honeypot, mixed $started, ?string $ip): void
    {
        $this->limit->ensureAllowed($ip);

        if ($this->guard->expired($started)) {
            throw ValidationException::withMessages(['email' => 'This form was open for a long time. Reload the page and try again.']);
        }

        $this->limit->hit($ip);

        $this->timebox->call(function () use ($data, $honeypot, $started, $ip): void {
            $hash = Hash::make($data->password);

            if ($this->guard->trapped($honeypot, $started, $ip) !== null) {
                return;
            }

            $this->register($data, $hash, $ip);
        }, (int) config('auth.timebox_duration'));
    }

    private function register(RegistrationData $data, string $hash, ?string $ip): void
    {
        $existing = User::withTrashed()->where('email', $data->email)->first();

        if ($existing !== null) {
            $this->notifyExisting($existing, $ip);

            return;
        }

        try {
            $user = DB::transaction(function () use ($data, $hash): User {
                $user = (new User)->forceFill([
                    'email' => $data->email,
                    'username' => $data->username,
                    'password' => $hash,
                ]);
                $user->save();

                UserRegistered::dispatch($user->id);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            $this->lostRace($data, $ip);

            return;
        }

        Log::channel('security')->info('auth.registered', ['user' => $user->ulid, 'ip_hash' => IpHash::of($ip)]);
        $user->sendEmailVerificationNotification();
    }

    /**
     * A concurrent registration took the email or the username between validation and insert.
     */
    private function lostRace(RegistrationData $data, ?string $ip): void
    {
        $byEmail = User::withTrashed()->where('email', $data->email)->first();

        if ($byEmail !== null) {
            $this->notifyExisting($byEmail, $ip);

            return;
        }

        throw ValidationException::withMessages(['username' => 'That username is taken. Pick another.']);
    }

    /**
     * One notice an hour per account, so nobody can flood an inbox by registering its address.
     */
    private function notifyExisting(User $existing, ?string $ip): void
    {
        Log::channel('security')->info('auth.registration_existing_email', ['user' => $existing->ulid, 'ip_hash' => IpHash::of($ip)]);

        $key = 'register:notice:'.$existing->id;
        if (! RateLimiter::tooManyAttempts($key, 1)) {
            RateLimiter::hit($key, self::NOTICE_WINDOW);
            $existing->notify(new RegistrationAttemptNotification);
        }
    }
}
