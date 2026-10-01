<?php

namespace App\Domain\Notifications\Data;

final readonly class UpdateEmailPreferencesData
{
    /** @param array<string, bool> $emailCategories */
    public function __construct(public bool $emailEnabled, public array $emailCategories) {}
}
