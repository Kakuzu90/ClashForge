<?php

namespace App\Domain\Auth\Data;

/**
 * One page of the admin user list, newest account first, with the cursors either side.
 */
final readonly class AdminUserSliceData
{
    /**
     * @param  list<AdminUserRowData>  $entries
     */
    public function __construct(
        public array $entries,
        public ?string $newerCursor,
        public ?string $olderCursor,
    ) {}
}
