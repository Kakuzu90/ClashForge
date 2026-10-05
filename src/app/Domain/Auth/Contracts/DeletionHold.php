<?php

namespace App\Domain\Auth\Contracts;

/**
 * Something another module needs settled before an account is anonymised (specs/23 §1: an open
 * ownership dispute; open marketplace orders join with P6). Implementations are tagged
 * `DeletionHold::HOLD_TAG` in their own module's provider, so Auth never references them.
 */
interface DeletionHold
{
    public const HOLD_TAG = 'auth.deletion_holds';

    /**
     * Why the account's deletion waits, in words for its owner, or null when nothing holds it.
     */
    public function reasonFor(int $userId): ?string;
}
