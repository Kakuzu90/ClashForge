<?php

namespace App\Http\Data\Admin;

use App\Domain\Auth\Data\AdminUserDetailData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Admin/Users/Show: the account, its profile summary and the latest audit entries
 * about it (newest first, at most `platform.admin.audit_trail_limit`).
 */
#[TypeScript]
class AdminUserShowPageData extends Data
{
    /**
     * @param  list<AuditTrailEntryData>  $auditTrail
     */
    public function __construct(
        public AdminUserDetailData $user,
        public ?string $displayName,
        public ?string $avatarUrl,
        public array $auditTrail,
        public bool $moreAuditEntries,
    ) {}
}
