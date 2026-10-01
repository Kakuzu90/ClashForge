<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Data\EmailChangeLinkData;
use App\Domain\Auth\Enums\EmailChangeOutcome;
use App\Domain\Auth\Events\EmailVerified;
use App\Domain\Auth\Notifications\EmailChangeAttemptNotification;
use App\Domain\Auth\Notifications\EmailChangedNotification;
use App\Domain\Auth\Notifications\EmailChangeLinkNotification;
use App\Domain\Auth\Notifications\EmailChangeRejectedNotification;
use App\Models\User;
use App\Support\Privacy\EmailMask;
use App\Support\Privacy\IpHash;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Changing the account email (FR-AUTH-8, specs/04 §4). Requests and resends take the current
 * password. The new address waits in `pending_email`
 * until its link is confirmed by the signed-in account. A taken address is stored and answered
 * like any other, so the form and the page say nothing about it; the two inboxes involved are told
 * instead (specs/23 §1, specs/11 "Account enumeration").
 */
class EmailChangeService
{
    private const NOTICE_WINDOW = 3600;

    public function __construct(
        private readonly SessionService $sessions,
        private readonly EmailChangeLimit $limit,
    ) {}

    /**
     * @throws ValidationException
     */
    public function request(User $user, string $email, string $currentPassword, ?string $ip): void
    {
        Gate::forUser($user)->authorize('changeEmail', $user);
        $this->checkPassword($user, $currentPassword, $ip);

        if (strtolower($email) === strtolower($user->email)) {
            throw ValidationException::withMessages(['email' => 'That is already your email address.']);
        }

        $this->limit->hit($user, $ip);
        $user->forceFill(['pending_email' => $email, 'pending_email_requested_at' => Date::now()])->save();
        $this->deliver($user, $email, $ip);
    }

    /**
     * Sends the pending change again; false when nothing is pending.
     */
    public function resend(User $user, string $currentPassword, ?string $ip): bool
    {
        Gate::forUser($user)->authorize('changeEmail', $user);
        $this->checkPassword($user, $currentPassword, $ip);

        $pending = $user->pending_email;

        if ($pending === null) {
            return false;
        }

        $this->limit->hit($user, $ip);
        $this->deliver($user, $pending, $ip);

        return true;
    }

    public function cancel(User $user): void
    {
        Gate::forUser($user)->authorize('changeEmail', $user);

        if ($user->pending_email !== null) {
            $user->forceFill(['pending_email' => null, 'pending_email_requested_at' => null])->save();
            Log::channel('security')->info('auth.email_change_cancelled', ['user' => $user->ulid]);
        }
    }

    /**
     * What the link points at, for the account signed in on this browser. The route's signature
     * check has already run.
     */
    public function inspect(string $ulid, string $hash, User $viewer): EmailChangeLinkData
    {
        // Another account's link says nothing about that account.
        if (strtolower($ulid) !== strtolower($viewer->ulid)) {
            return new EmailChangeLinkData(EmailChangeOutcome::WrongAccount);
        }

        if ($viewer->pending_email !== null && hash_equals(sha1(strtolower($viewer->pending_email)), $hash)) {
            return new EmailChangeLinkData(EmailChangeOutcome::Pending, $viewer->username, EmailMask::of($viewer->pending_email));
        }

        if (hash_equals(sha1(strtolower($viewer->email)), $hash)) {
            return new EmailChangeLinkData(EmailChangeOutcome::AlreadyChanged);
        }

        return new EmailChangeLinkData(EmailChangeOutcome::Invalid);
    }

    /**
     * One conditional update, so two presses change once. Every other session ends and the
     * remember token is cycled (specs/04 §4); the controller gives this browser a new session id.
     */
    public function confirm(string $ulid, string $hash, User $viewer, ?string $sessionId, ?string $ip): EmailChangeOutcome
    {
        $link = $this->inspect($ulid, $hash, $viewer);

        if ($link->outcome !== EmailChangeOutcome::Pending) {
            return $link->outcome;
        }

        Gate::forUser($viewer)->authorize('changeEmail', $viewer);

        $old = $viewer->email;
        $new = (string) $viewer->pending_email;
        $wasVerified = $viewer->email_verified_at !== null;

        if ($this->takenByAnother($new, $viewer)) {
            return $this->rejectAtConfirm($viewer, $ip);
        }

        try {
            $changed = DB::transaction(function () use ($viewer, $new, $wasVerified, $sessionId): bool {
                $updated = User::query()->whereKey($viewer->id)->where('pending_email', $new)->update([
                    'email' => $new,
                    'email_verified_at' => Date::now(),
                    'pending_email' => null,
                    'pending_email_requested_at' => null,
                ]);

                if ($updated !== 1) {
                    return false;
                }

                $viewer->refresh();
                $this->sessions->endOthers($viewer, $sessionId);

                if (! $wasVerified) {
                    EmailVerified::dispatch($viewer->id);
                }

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            // Another account took the address between the check and the update.
            return $this->rejectAtConfirm($viewer->refresh(), $ip);
        }

        if (! $changed) {
            return EmailChangeOutcome::Invalid;
        }

        Log::channel('security')->info('auth.email_changed', ['user' => $viewer->ulid, 'ip_hash' => IpHash::of($ip)]);

        $masked = EmailMask::of($new);
        Notification::route('mail', $old)->notify(new EmailChangedNotification($viewer->username, $masked, toOldAddress: true));
        $viewer->notify(new EmailChangedNotification($viewer->username, $masked, toOldAddress: false));

        return EmailChangeOutcome::Changed;
    }

    /**
     * A free address gets the link; a taken one gets the attempt notice, and the requester's
     * current address hears that the change cannot go through. Each email is capped per inbox and hour.
     */
    private function deliver(User $user, string $email, ?string $ip): void
    {
        $existing = User::withTrashed()->where('email', $email)->whereKeyNot($user->id)->first();

        Log::channel('security')->info('auth.email_change_requested', ['user' => $user->ulid, 'taken' => $existing !== null, 'ip_hash' => IpHash::of($ip)]);

        if ($existing === null) {
            // Per inbox too, so many accounts cannot flood one address with links.
            $this->once('email-change:link:'.sha1(strtolower($email)), fn () => Notification::route('mail', $email)->notify(new EmailChangeLinkNotification($user->username, $user->ulid, $email)), 'platform.auth.email_change_links_per_address_per_hour');

            return;
        }

        $this->once('email-change:attempt:'.$existing->id, fn () => $existing->notify(new EmailChangeAttemptNotification));
        $this->once('email-change:rejected:'.$user->id, fn () => $user->notify(new EmailChangeRejectedNotification(EmailMask::of($email))));
    }

    /**
     * The current password, typed with the request, is the re-confirmation (specs/11).
     *
     * @throws ValidationException
     */
    private function checkPassword(User $user, string $currentPassword, ?string $ip): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            Log::channel('security')->warning('auth.password_confirm_failed', ['user' => $user->ulid, 'ip_hash' => IpHash::of($ip)]);

            throw ValidationException::withMessages(['current_password' => 'That is not your current password.']);
        }
    }

    private function rejectAtConfirm(User $user, ?string $ip): EmailChangeOutcome
    {
        $user->forceFill(['pending_email' => null, 'pending_email_requested_at' => null])->save();
        Log::channel('security')->info('auth.email_change_taken', ['user' => $user->ulid, 'ip_hash' => IpHash::of($ip)]);

        return EmailChangeOutcome::Taken;
    }

    private function takenByAnother(string $email, User $user): bool
    {
        return User::withTrashed()->where('email', $email)->whereKeyNot($user->id)->exists();
    }

    /**
     * @param  callable(): void  $send
     */
    private function once(string $key, callable $send, string $limit = 'platform.auth.email_change_notice_per_hour'): void
    {
        if (RateLimiter::tooManyAttempts($key, (int) config($limit))) {
            return;
        }

        RateLimiter::hit($key, self::NOTICE_WINDOW);
        $send();
    }
}
