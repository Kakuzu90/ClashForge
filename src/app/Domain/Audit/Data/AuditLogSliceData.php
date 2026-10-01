<?php

namespace App\Domain\Audit\Data;

/**
 * One page of the audit log, newest first, with the cursors for the pages either side.
 */
final readonly class AuditLogSliceData
{
    /**
     * @param  list<AuditLogRecordData>  $entries
     */
    public function __construct(
        public array $entries,
        public ?string $newerCursor,
        public ?string $olderCursor,
    ) {}
}
