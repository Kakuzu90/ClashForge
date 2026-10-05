<?php

namespace App\Domain\Auth\Contracts;

/**
 * Another module's part of anonymising an account (specs/08 §6), inside the anonymisation
 * transaction. Implementations are tagged `DeletionStep::STEP_TAG` in their own module's provider.
 */
interface DeletionStep
{
    public const STEP_TAG = 'auth.deletion_steps';

    /**
     * Locks the rows run() will change. Called before the account row is locked, since other
     * modules lock their rows first and the account after.
     */
    public function lock(int $userId): void;

    /**
     * Clears or releases the module's data for the account, before Auth anonymises the row.
     */
    public function run(int $userId): void;
}
