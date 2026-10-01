<?php

namespace App\Http\Data\Admin;

use App\Domain\Audit\Data\AuditLogSliceData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The deferred `log` prop of Admin/AuditLog: one page, newest first.
 */
#[TypeScript]
class AuditLogListData extends Data
{
    /**
     * @param  list<AuditLogEntryData>  $entries
     */
    public function __construct(
        public array $entries,
        public ?string $newerCursor,
        public ?string $olderCursor,
    ) {}

    public static function fromPage(AuditLogSliceData $page): self
    {
        return new self(
            entries: array_map(AuditLogEntryData::fromRecord(...), $page->entries),
            newerCursor: $page->newerCursor,
            olderCursor: $page->olderCursor,
        );
    }
}
