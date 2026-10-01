<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Data\SessionData;
use App\Domain\Auth\Support\CountryName;
use App\Domain\Auth\Support\RememberCookie;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The signed-in browsers of an account (FR-AUTH-7, specs/04 §4): listing, revoking one or all
 * others, and the 30-day absolute cap. Rows are the database session driver's own.
 */
class SessionService
{
    /** @param callable(): bool $write */
    public function persistFor(int $userId, callable $write): bool
    {
        return DB::transaction(function () use ($userId, $write): bool {
            $account = User::query()->whereKey($userId)->lockForUpdate()->first();
            if ($account === null || ! Gate::forUser($account)->allows('persistSession', $account)) {
                return false;
            }

            return $write();
        });
    }

    /**
     * Unix time of the sign-in that started this session, kept in the session itself.
     */
    public const SIGNED_IN_AT = 'auth.signed_in_at';

    /**
     * @return list<SessionData>
     */
    public function listFor(User $user, ?string $currentId): array
    {
        $sessions = [];

        foreach ($this->liveRows($user)->orderByDesc('last_activity')->get() as $row) {
            $sessions[] = new SessionData(
                key: self::keyOf((string) $row->id),
                deviceLabel: is_string($row->device_label) ? $row->device_label : 'Unknown device',
                country: CountryName::of(is_string($row->country_code) ? $row->country_code : null),
                lastActiveAt: Date::createFromTimestamp((int) $row->last_activity)->toIso8601String(),
                signedInAt: $row->created_at === null ? null : CarbonImmutable::parse((string) $row->created_at)->toIso8601String(),
                isCurrent: $row->id === $currentId,
            );
        }

        // The current browser first, then most recent.
        usort($sessions, fn (SessionData $a, SessionData $b): int => (int) $b->isCurrent <=> (int) $a->isCurrent);

        return $sessions;
    }

    /**
     * Signs out one other browser. A key that is not one of the account's other sessions is a 404,
     * like any foreign id (specs/04 §3 IDOR).
     */
    public function revoke(User $user, string $key, ?string $currentId): void
    {
        DB::transaction(function () use ($user, $key, $currentId): void {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('manageSessions', $user);

            $id = $this->liveRows($user)->pluck('id')->first(fn (mixed $id): bool => $id !== $currentId && hash_equals(self::keyOf((string) $id), $key));

            if ($id === null) {
                throw new NotFoundHttpException;
            }

            $this->rows($user)->where('id', $id)->delete();
            RememberCookie::cycle($user);

            $this->log('auth.session_revoked', $user, ['count' => 1]);
        });
    }

    public function revokeOthers(User $user, ?string $currentId): int
    {
        return DB::transaction(function () use ($user, $currentId): int {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('manageSessions', $user);

            $count = $this->endOthers($user, $currentId);
            $this->log('auth.session_revoked', $user, ['count' => $count]);

            return $count;
        });
    }

    /**
     * Deletes every other session row and kills their remember cookies. For callers that have
     * already authorized the change (password change).
     */
    public function endOthers(User $user, ?string $currentId): int
    {
        $others = fn (Builder $query) => $query->when($currentId !== null, fn (Builder $q) => $q->where('id', '!=', $currentId));

        // Counts the browsers that were signed in; idle-expired rows the GC has not swept yet go too.
        $count = $others($this->liveRows($user))->count();
        $others($this->rows($user))->delete();
        RememberCookie::cycle($user);

        return $count;
    }

    /**
     * How many browsers are signed in to the account now (the admin user detail).
     */
    public function liveCount(User $user): int
    {
        return $this->liveRows($user)->count();
    }

    /**
     * True once a session is older than the absolute cap (specs/04 §4), however active it is.
     */
    public function pastAbsoluteLifetime(int $signedInAt): bool
    {
        $limit = (int) config('platform.auth.absolute_session_days') * 86400;

        return Date::now()->getTimestamp() - $signedInAt >= $limit;
    }

    /**
     * An opaque, stable handle for a session id: HMAC with the app key, so it identifies the row
     * without being usable as a cookie value.
     */
    public static function keyOf(string $sessionId): string
    {
        return substr(hash_hmac('sha256', $sessionId, (string) config('app.key')), 0, 32);
    }

    private function rows(User $user): Builder
    {
        return DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id);
    }

    /**
     * Rows still inside the idle lifetime: the session GC runs by lottery, so expired rows can
     * linger and would show as signed in.
     */
    private function liveRows(User $user): Builder
    {
        return $this->rows($user)->where('last_activity', '>', Date::now()->subMinutes((int) config('session.lifetime'))->getTimestamp());
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function log(string $message, User $user, array $extra = []): void
    {
        Log::channel('security')->info($message, ['user' => $user->ulid, 'ip_hash' => IpHash::of(request()->ip()), ...$extra]);
    }
}
