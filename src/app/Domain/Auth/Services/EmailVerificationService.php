<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Data\VerificationLinkData;
use App\Domain\Auth\Enums\EmailVerificationOutcome;
use App\Domain\Auth\Events\EmailVerified;
use App\Domain\Auth\Notifications\VerifyEmailNotification;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Confirming an email (FR-AUTH-3). Opening the link changes nothing: mail gateways open links to
 * scan them. The person confirms with a button, which is a POST (security review, 2026-10-01).
 * The route's signature check has run before either call.
 */
class EmailVerificationService
{
    public function __construct(private readonly SessionService $sessions) {}

    public function inspect(string $ulid, string $hash): VerificationLinkData
    {
        $user = $this->owner($ulid, $hash);

        if ($user === null) {
            return new VerificationLinkData(EmailVerificationOutcome::Invalid, null);
        }

        return new VerificationLinkData(
            $user->email_verified_at === null ? EmailVerificationOutcome::Pending : EmailVerificationOutcome::AlreadyVerified,
            $user->username,
        );
    }

    /**
     * Confirms once and ends every session of the account except the confirming browser's own,
     * so whoever registered someone else's address loses the account the moment its owner
     * confirms it.
     */
    public function confirm(string $ulid, string $hash, ?string $ip, ?User $viewer, ?string $sessionId): EmailVerificationOutcome
    {
        return DB::transaction(function () use ($ulid, $hash, $ip, $viewer, $sessionId): EmailVerificationOutcome {
            // Reload the identity under the same lock as purge; a stale link cannot verify a tombstone.
            $user = User::query()->whereUlid($ulid)->lockForUpdate()->first();
            if ($user === null || ! hash_equals(sha1(strtolower($user->email)), $hash)) {
                return EmailVerificationOutcome::Invalid;
            }
            if ($user->email_verified_at !== null) {
                return EmailVerificationOutcome::AlreadyVerified;
            }

            $user->forceFill(['email_verified_at' => Date::now()])->save();
            $this->sessions->endOthers($user, $viewer?->is($user) ? $sessionId : null);
            EmailVerified::dispatch($user->id);
            Log::channel('security')->info('auth.email_verified', ['user' => $user->ulid, 'ip_hash' => IpHash::of($ip)]);

            return EmailVerificationOutcome::Verified;
        });
    }

    /**
     * A fresh link for a signed-in account that has not confirmed yet; false when there is nothing
     * to send.
     */
    public function resend(User $user): bool
    {
        if ($user->email_verified_at !== null) {
            return false;
        }

        $user->notify(new VerifyEmailNotification);

        return true;
    }

    /**
     * The account the link names, if the email hash still matches its address.
     */
    private function owner(string $ulid, string $hash): ?User
    {
        $user = User::query()->whereUlid($ulid)->first();

        return $user !== null && hash_equals(sha1(strtolower($user->email)), $hash) ? $user : null;
    }
}
