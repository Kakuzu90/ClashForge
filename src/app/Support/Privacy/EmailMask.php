<?php

namespace App\Support\Privacy;

/**
 * An address shown back to its owner without putting it whole in page props (specs/11 "Data
 * exposure via page props"): the first character of the local part, then the domain.
 */
final class EmailMask
{
    public static function of(string $email): string
    {
        $at = strrpos($email, '@');

        if ($at === false || $at === 0) {
            return '***';
        }

        return mb_substr($email, 0, 1).'***'.substr($email, $at);
    }
}
