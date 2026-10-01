<?php

namespace App\Domain\Auth\Data;

use App\Domain\Auth\Models\UsernameHistory;
use Closure;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

/**
 * Username rules (FR-AUTH-1): 3 to 20 lowercase letters, numbers or underscores, not reserved,
 * unique ignoring case (the column is citext / NOCASE, deleted accounts included), and not held in
 * `username_history` (FR-PROFILE-7): a deleted account's name forever, a changed-away name for
 * `username_reservation_days`, except for the account that released it.
 */
final class UsernameFieldRules
{
    public const MIN = 3;

    public const MAX = 20;

    public const TAKEN = 'That username is taken. Pick another.';

    /**
     * @return list<mixed>
     */
    public static function forRegistration(): array
    {
        return self::rules(null);
    }

    /**
     * @return list<mixed>
     */
    public static function forChange(int $userId): array
    {
        return self::rules($userId);
    }

    public static function isReserved(string $username): bool
    {
        /** @var list<string> $reserved */
        $reserved = config('platform.auth.reserved_usernames');

        return in_array(strtolower($username), $reserved, true);
    }

    /**
     * Held for someone else: reserved forever, or released by another account inside the hold.
     *
     * @phpstan-impure Reservations may commit while a registration insert or a change waits.
     */
    public static function isHeld(string $username, ?int $exceptUserId = null): bool
    {
        $since = Date::now()->subDays((int) config('platform.auth.username_reservation_days'));

        return UsernameHistory::query()
            ->where('username', $username)
            ->where(fn ($held) => $held->where('reserved_forever', true)->orWhere(fn ($recent) => $recent
                ->where('released_at', '>', $since)
                ->when($exceptUserId !== null, fn ($q) => $q->where('user_id', '!=', $exceptUserId))))
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'username.regex' => 'Use lowercase letters, numbers and underscores only.',
            'username.unique' => self::TAKEN,
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function rules(?int $userId): array
    {
        // bail: the table checks run only on a storable value; bytes such as %FF make Postgres
        // raise instead of finding nothing.
        return [
            'bail',
            'required',
            'string',
            'min:'.self::MIN,
            'max:'.self::MAX,
            'regex:/^[a-z0-9_]+$/D',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && self::isReserved($value)) {
                    $fail('That username is reserved. Pick another.');
                }
            },
            Rule::unique('users', 'username')->ignore($userId),
            function (string $attribute, mixed $value, Closure $fail) use ($userId): void {
                if (is_string($value) && self::isHeld($value, $userId)) {
                    $fail(self::TAKEN);
                }
            },
        ];
    }
}
