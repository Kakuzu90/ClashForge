<?php

namespace App\Domain\Auth\Services;

use App\Domain\Audit\Data\AuditActorData;
use App\Domain\Audit\Data\AuditEntryData;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Auth\Data\UsernameFieldRules;
use App\Domain\Auth\Data\UsernameSettingsData;
use App\Domain\Auth\Models\UsernameHistory;
use App\Domain\Users\Services\CacheInvalidator;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Changing the username (FR-PROFILE-7): once per `username_change_days`, with the current password
 * inline. The old name goes into `username_history`, held for this account for
 * `username_reservation_days` while `/u/{old}` redirects (specs/23 §1).
 */
class UsernameChangeService
{
    private const DEADLOCK = '40P01';

    public function __construct(private readonly AuditLogger $audit) {}

    public function settingsFor(User $user): UsernameSettingsData
    {
        $next = $this->nextChangeAt($user);

        return new UsernameSettingsData(
            username: $user->username,
            canChange: $next === null && Gate::forUser($user)->allows('changeUsername', $user),
            needsVerifiedEmail: ! $user->hasVerifiedEmail(),
            nextChangeAt: $next?->toIso8601String(),
            changeDays: (int) config('platform.auth.username_change_days'),
            reservationDays: (int) config('platform.auth.username_reservation_days'),
            minLength: UsernameFieldRules::MIN,
            maxLength: UsernameFieldRules::MAX,
        );
    }

    /**
     * @throws ValidationException
     */
    public function change(User $actor, string $username, #[\SensitiveParameter] string $currentPassword, ?string $ip): void
    {
        try {
            [$old, $ulid] = DB::transaction(function () use ($actor, $username, $currentPassword, $ip): array {
                $account = User::query()->lockForUpdate()->findOrFail($actor->id);
                // The locked row, so a sanction or deletion request that just committed counts.
                Gate::forUser($account)->authorize('changeUsername', $account);
                $this->checkPassword($account, $currentPassword, $ip);

                if (strtolower($username) === strtolower($account->username)) {
                    throw ValidationException::withMessages(['username' => 'That is already your username.']);
                }

                $next = $this->nextChangeAt($account);
                if ($next !== null) {
                    throw ValidationException::withMessages(['username' => 'You can change your username again on '.$next->format('j F Y').'.']);
                }

                // Validation ran before the lock; a hold may have committed since.
                if (UsernameFieldRules::isHeld($username, $account->id)) {
                    throw ValidationException::withMessages(['username' => UsernameFieldRules::TAKEN]);
                }

                $old = $account->username;
                $now = Date::now();
                $account->forceFill(['username' => $username, 'username_changed_at' => $now])->save();

                // The update can wait on the unique index for another account releasing this name;
                // its hold commits with that release (specs/08 §6).
                if (UsernameFieldRules::isHeld($username, $account->id)) {
                    throw ValidationException::withMessages(['username' => UsernameFieldRules::TAKEN]);
                }

                UsernameHistory::query()->create(['user_id' => $account->id, 'username' => $old, 'released_at' => $now]);

                $this->audit->record(new AuditEntryData(
                    actor: new AuditActorData(id: $account->id, role: $account->role->value),
                    action: AuditAction::UsernameChanged,
                    subject: AuditSubject::User,
                    subjectId: $account->id,
                    before: ['username' => $old],
                    after: ['username' => $username],
                ));

                DB::afterCommit(function () use ($old, $username): void {
                    CacheInvalidator::profile($old);
                    CacheInvalidator::profile($username);
                });

                return [$old, $account->ulid];
            });
        } catch (UniqueConstraintViolationException) {
            // Another account took the name between the check and the update.
            throw ValidationException::withMessages(['username' => UsernameFieldRules::TAKEN]);
        } catch (QueryException $e) {
            // Two accounts swapping names wait on each other's index entry; Postgres aborts one.
            if ($e->getCode() !== self::DEADLOCK) {
                throw $e;
            }

            throw ValidationException::withMessages(['username' => UsernameFieldRules::TAKEN]);
        }

        Log::channel('security')->info('auth.username_changed', ['user' => $ulid, 'from' => $old, 'to' => $username, 'ip_hash' => IpHash::of($ip)]);
    }

    /**
     * When the next change opens, or null when one is allowed now (none made yet included).
     */
    public function nextChangeAt(User $user): ?CarbonImmutable
    {
        $next = $user->username_changed_at?->addDays((int) config('platform.auth.username_change_days'));

        return $next !== null && $next->isFuture() ? $next : null;
    }

    /**
     * @throws ValidationException
     */
    private function checkPassword(User $account, #[\SensitiveParameter] string $currentPassword, ?string $ip): void
    {
        if ($account->password === null || ! Hash::check($currentPassword, $account->password)) {
            Log::channel('security')->warning('auth.password_confirm_failed', ['user' => $account->ulid, 'ip_hash' => IpHash::of($ip)]);

            throw ValidationException::withMessages(['current_password' => 'That is not your current password.']);
        }
    }
}
