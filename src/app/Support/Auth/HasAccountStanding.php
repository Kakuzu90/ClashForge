<?php

namespace App\Support\Auth;

/**
 * Lets edge modules (Media) check an account's write standing without depending on Auth
 * (specs/19 §2). Implemented by App\Models\User from its effective status (specs/04 §1).
 */
interface HasAccountStanding
{
    public function allowsAccountWrites(): bool;

    public function allowsContentWrites(): bool;

    /**
     * Holds at least one verified (or disputed, still held) CoC account: the bar for publishing a
     * base, and so for uploading its replay video (specs/04 §1).
     */
    public function hasVerifiedCocAccount(): bool;
}
