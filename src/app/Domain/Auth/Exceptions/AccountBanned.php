<?php

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

/**
 * Right password, banned account (specs/04 §1). Raised only after the password check, so a wrong
 * password on a banned account gets the usual failure and the status stays private.
 */
class AccountBanned extends RuntimeException
{
    public function __construct(public readonly ?string $reason)
    {
        parent::__construct('Account is banned.');
    }
}
