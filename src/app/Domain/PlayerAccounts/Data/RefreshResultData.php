<?php

namespace App\Domain\PlayerAccounts\Data;

use App\Domain\PlayerAccounts\Enums\RefreshOutcome;

/**
 * A manual refresh's answer for the controller (P2-20): `waitSeconds` is set when a limit refused it.
 */
final readonly class RefreshResultData
{
    public function __construct(
        public RefreshOutcome $outcome,
        public ?int $waitSeconds = null,
    ) {}

    /**
     * The message the owner sees; a refusal says how long is left, in whole minutes rounded up.
     */
    public function message(): string
    {
        if ($this->waitSeconds === null) {
            return $this->outcome->label();
        }

        $minutes = max(1, (int) ceil($this->waitSeconds / 60));

        return $this->outcome->label().' You can refresh again in '.($minutes === 1 ? '1 minute.' : "{$minutes} minutes.");
    }
}
