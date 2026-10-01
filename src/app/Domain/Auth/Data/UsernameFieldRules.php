<?php

namespace App\Domain\Auth\Data;

use Closure;
use Illuminate\Validation\Rule;

/**
 * Username rules (FR-AUTH-1): 3 to 20 lowercase letters, numbers or underscores, not reserved,
 * unique ignoring case (the column is citext / NOCASE, deleted accounts included). Names released
 * by a username change join with P1-09.
 */
final class UsernameFieldRules
{
    public const MIN = 3;

    public const MAX = 20;

    /**
     * @return list<mixed>
     */
    public static function forRegistration(): array
    {
        return [
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
            Rule::unique('users', 'username'),
        ];
    }

    public static function isReserved(string $username): bool
    {
        /** @var list<string> $reserved */
        $reserved = config('platform.auth.reserved_usernames');

        return in_array(strtolower($username), $reserved, true);
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'username.regex' => 'Use lowercase letters, numbers and underscores only.',
            'username.unique' => 'That username is taken. Pick another.',
        ];
    }
}
