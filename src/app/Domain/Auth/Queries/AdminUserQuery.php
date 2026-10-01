<?php

namespace App\Domain\Auth\Queries;

use App\Domain\Auth\Data\AdminUserDetailData;
use App\Domain\Auth\Data\AdminUserFilterData;
use App\Domain\Auth\Data\AdminUserRowData;
use App\Domain\Auth\Data\AdminUserSliceData;
use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Services\SessionService;
use App\Models\User;
use App\Support\Pagination\CursorShape;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Date;

/**
 * The admin user list and detail (FR-ADMIN-2, specs/12 §4). Staff see every account here,
 * banned, pending deletion and soft deleted included (specs/04 §3), except their own and super
 * admins' (owner decision, 2026-10-01). Newest account first, paged by id (sign-up order).
 */
class AdminUserQuery
{
    private const CURSOR_COLUMN = 'id';

    public function __construct(private readonly SessionService $sessions) {}

    public static function acceptsCursor(string $cursor): bool
    {
        return CursorShape::accepts($cursor, self::CURSOR_COLUMN);
    }

    public function page(User $viewer, AdminUserFilterData $filters, int $perPage, ?string $cursor = null): AdminUserSliceData
    {
        $query = $this->visibleTo($viewer)->orderByDesc(self::CURSOR_COLUMN);

        $this->filter($query, $filters);

        $page = $query->cursorPaginate($perPage, cursor: Cursor::fromEncoded($cursor));

        $entries = [];
        foreach ($page->items() as $user) {
            /** @var User $user */
            $status = $user->effectiveStatus();
            $entries[] = new AdminUserRowData(
                ulid: $user->ulid,
                username: $user->username,
                email: $user->email,
                emailVerified: $user->email_verified_at !== null,
                roleLabel: $user->role->label(),
                statusLabel: $status->label(),
                statusTone: self::tone($status),
                joinedAt: $user->created_at->toIso8601String(),
                lastSignInAt: $user->last_login_at?->toIso8601String(),
                deleted: $user->deleted_at !== null,
            );
        }

        return new AdminUserSliceData(
            entries: $entries,
            newerCursor: $page->previousCursor()?->encode(),
            olderCursor: $page->nextCursor()?->encode(),
        );
    }

    /**
     * The account behind `/admin/users/{ulid}`, soft deleted included; null when there is none or
     * the viewer may not see it.
     */
    public function find(User $viewer, string $ulid): ?User
    {
        return $this->visibleTo($viewer)->where('ulid', strtolower($ulid))->first();
    }

    /**
     * @return Builder<User>
     */
    private function visibleTo(User $viewer): Builder
    {
        return User::query()->withTrashed()
            ->whereKeyNot($viewer->getKey())
            ->where('role', '!=', Role::SuperAdmin->value);
    }

    public function detail(User $user): AdminUserDetailData
    {
        $status = $user->effectiveStatus();
        $sanctioned = $status !== UserStatus::Active;

        return new AdminUserDetailData(
            ulid: $user->ulid,
            username: $user->username,
            email: $user->email,
            emailVerifiedAt: $user->email_verified_at?->toIso8601String(),
            roleLabel: $user->role->label(),
            statusLabel: $status->label(),
            statusTone: self::tone($status),
            statusReason: $sanctioned ? $user->status_reason : null,
            statusEndsAt: $sanctioned ? $user->status_expires_at?->toIso8601String() : null,
            joinedAt: $user->created_at->toIso8601String(),
            lastSignInAt: $user->last_login_at?->toIso8601String(),
            activeSessions: $this->sessions->liveCount($user),
            deletedAt: $user->deleted_at?->toIso8601String(),
        );
    }

    /**
     * @param  Builder<User>  $query
     */
    private function filter(Builder $query, AdminUserFilterData $filters): void
    {
        if ($filters->search !== null) {
            if (str_contains($filters->search, '@')) {
                // citext / NOCASE: equality ignores case.
                $query->where('email', $filters->search);
            } else {
                $pattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters->search).'%';
                // LIKE ignores case on citext and, for ASCII, on SQLite; `_` is common in usernames.
                $query->whereRaw("username like ? escape '\\'", [$pattern]);
            }
        }

        if ($filters->role !== null) {
            $query->where('role', $filters->role->value);
        }

        if ($filters->status !== null) {
            $this->whereEffectiveStatus($query, $filters->status);
        }
    }

    /**
     * Mirrors UserStatus::effective(): a timed restriction or suspension whose end has passed
     * counts as active, before the expiry job clears it (specs/23 §7).
     *
     * @param  Builder<User>  $query
     */
    private function whereEffectiveStatus(Builder $query, UserStatus $status): void
    {
        $now = Date::now();
        $timed = [UserStatus::Restricted->value, UserStatus::Suspended->value];

        match ($status) {
            UserStatus::Active => $query->where(fn (Builder $q) => $q
                ->where('status', UserStatus::Active->value)
                ->orWhere(fn (Builder $q) => $q->whereIn('status', $timed)->where('status_expires_at', '<=', $now))),
            UserStatus::Restricted, UserStatus::Suspended => $query
                ->where('status', $status->value)
                ->where(fn (Builder $q) => $q->whereNull('status_expires_at')->orWhere('status_expires_at', '>', $now)),
            default => $query->where('status', $status->value),
        };
    }

    private static function tone(UserStatus $status): string
    {
        return match ($status) {
            UserStatus::Active => 'success',
            UserStatus::Restricted => 'warning',
            UserStatus::Suspended, UserStatus::Banned => 'danger',
            UserStatus::PendingDeletion => 'neutral',
        };
    }
}
