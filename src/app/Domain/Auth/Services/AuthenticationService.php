<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Exceptions\AccountBanned;
use App\Domain\Users\Services\CacheInvalidator;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Credential checks for Fortify's login pipeline (specs/04 §4, specs/11 "Authentication attacks").
 * An unknown email costs one password hash check, the same as a known one, so response time does
 * not reveal which addresses have accounts. A banned account is refused only once the password
 * matched, so its status is never shown to someone guessing (specs/04 §1).
 */
class AuthenticationService
{
    private const TIMING_HASH_KEY = 'auth.timing_hash';

    /**
     * @throws AccountBanned
     */
    public function attempt(string $email, string $password): ?User
    {
        return DB::transaction(fn (): ?User => $this->checkCredentials($email, $password));
    }

    private function checkCredentials(string $email, string $password): ?User
    {
        $user = User::query()->where('email', $email)->lockForUpdate()->first();

        if ($user === null || $user->password === null) {
            Hash::check($password, $this->timingHash());
            $this->logFailure(null);

            return null;
        }

        if (! Hash::check($password, $user->password)) {
            $this->logFailure($user);

            return null;
        }

        if (! $user->effectiveStatus()->canLogIn()) {
            Log::channel('security')->warning('auth.login_blocked', [
                'user' => $user->ulid,
                'ip_hash' => IpHash::of(request()->ip()),
            ]);

            throw new AccountBanned($user->status_reason);
        }

        // Keeps stored hashes at the configured cost (bcrypt 12) as it changes.
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }

        $this->cancelDeletion($user);

        return $user;
    }

    private function cancelDeletion(User $account): void
    {
        if ($account->status !== UserStatus::PendingDeletion) {
            return;
        }

        $status = ($account->deletion_previous_status ?? UserStatus::Active)->effective($account->status_expires_at);
        $account->forceFill([
            'status' => $status, 'deletion_requested_at' => null, 'deletion_previous_status' => null,
            'status_reason' => $status === UserStatus::Active ? null : $account->status_reason,
            'status_expires_at' => $status === UserStatus::Active ? null : $account->status_expires_at,
        ])->save();

        DB::afterCommit(fn () => CacheInvalidator::profile($account->username));
        Log::channel('security')->info('auth.deletion_cancelled', ['user' => $account->ulid]);
    }

    public function recordLogin(User $user, ?string $ip): void
    {
        $user->forceFill([
            'last_login_at' => Date::now(),
            'last_login_ip_hash' => IpHash::of($ip),
        ])->saveQuietly();
    }

    /**
     * Logged here rather than from Fortify's `Failed` event, which carries no user when a custom
     * callback checks the password; the account is what spots guessing spread over many IPs.
     */
    private function logFailure(?User $user): void
    {
        Log::channel('security')->warning('auth.login_failed', [
            'user' => $user?->ulid,
            'ip_hash' => IpHash::of(request()->ip()),
        ]);
    }

    /**
     * A hash at the current cost, cached so the unknown-email path never pays for creating one.
     */
    private function timingHash(): string
    {
        $hash = Cache::get(self::TIMING_HASH_KEY);

        if (! is_string($hash) || Hash::needsRehash($hash)) {
            $hash = Hash::make(Str::random(40));
            Cache::put(self::TIMING_HASH_KEY, $hash, Date::now()->addDay());
        }

        return $hash;
    }
}
