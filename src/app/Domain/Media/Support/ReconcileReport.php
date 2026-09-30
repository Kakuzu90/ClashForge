<?php

namespace App\Domain\Media\Support;

/**
 * Counts from one media:reconcile-storage run.
 */
final class ReconcileReport
{
    public function __construct(
        public readonly int $scanned,
        public readonly int $flagged,
        public readonly int $deleted,
        public readonly int $missing,
    ) {}

    /**
     * @return array{scanned: int, flagged: int, deleted: int, missing: int}
     */
    public function toArray(): array
    {
        return [
            'scanned' => $this->scanned,
            'flagged' => $this->flagged,
            'deleted' => $this->deleted,
            'missing' => $this->missing,
        ];
    }
}
