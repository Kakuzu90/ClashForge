<?php

namespace App\Domain\Auth\Data;

use App\Domain\Auth\Services\DisposableEmailDomains;
use Closure;

/**
 * Rules for an address someone gives us to keep: registration and email change (FR-AUTH-1/8,
 * FR-AUTH-11). Whether another account already uses it is never a validation error: that answer
 * only goes to the inbox (specs/11 "Account enumeration").
 */
final class EmailFieldRules
{
    public const MAX = 255;

    /**
     * @return list<mixed>
     */
    public static function rules(): array
    {
        return ['required', 'string', 'email:rfc', 'max:'.self::MAX, function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && app(DisposableEmailDomains::class)->isDisposable($value)) {
                $fail('Use an email address you will keep. Throwaway inboxes are not accepted.');
            }
        }];
    }
}
