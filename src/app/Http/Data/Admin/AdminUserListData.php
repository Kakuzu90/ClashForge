<?php

namespace App\Http\Data\Admin;

use App\Domain\Auth\Data\AdminUserRowData;
use App\Domain\Auth\Data\AdminUserSliceData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The deferred `users` prop of Admin/Users/Index: one page, newest account first.
 */
#[TypeScript]
class AdminUserListData extends Data
{
    /**
     * @param  list<AdminUserRowData>  $entries
     */
    public function __construct(
        public array $entries,
        public ?string $newerCursor,
        public ?string $olderCursor,
    ) {}

    public static function fromSlice(AdminUserSliceData $slice): self
    {
        return new self($slice->entries, $slice->newerCursor, $slice->olderCursor);
    }
}
