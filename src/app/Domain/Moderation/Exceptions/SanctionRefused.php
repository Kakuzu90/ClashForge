<?php

namespace App\Domain\Moderation\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * A sanction that the account's current state does not allow, e.g. suspending a banned account
 * or lifting when nothing is active. A validation error on `sanction`, so a web request returns
 * to the form with the message shown to the acting admin.
 */
final class SanctionRefused extends ValidationException
{
    public static function because(string $message): self
    {
        return self::withMessages(['sanction' => $message]);
    }
}
