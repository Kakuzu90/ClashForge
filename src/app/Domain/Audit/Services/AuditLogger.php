<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Data\AuditEntryData;
use App\Domain\Audit\Models\AuditLog;
use App\Support\Privacy\IpHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * The only writer of `audit_logs` (specs/05 §4: called explicitly by the service that acts, never
 * from an observer, so the entry carries the why). Call it inside the acting service's
 * transaction, so the entry and the change commit or roll back together.
 *
 * The IP hash and user agent come from the current HTTP request; on the console they stay null.
 * The request id comes from the log context, so a queued job keeps the id of the request that
 * dispatched it.
 */
class AuditLogger
{
    private const USER_AGENT_LENGTH = 255;

    public function __construct(private readonly Request $request) {}

    public function record(AuditEntryData $entry): void
    {
        $http = $this->request->route() !== null;
        $requestId = Context::get('request_id');
        $userAgent = $http ? $this->request->userAgent() : null;

        $context = $entry->context;
        if ($entry->actor->via !== null) {
            $context = ['via' => $entry->actor->via, ...$context];
        }

        AuditLog::query()->create([
            'actor_id' => $entry->actor->id,
            'actor_role' => $entry->actor->role,
            'action' => $entry->action,
            'auditable_type' => $entry->subject,
            'auditable_id' => $entry->subjectId,
            'before' => $entry->before,
            'after' => $entry->after,
            'context' => $context,
            'ip_hash' => $http ? IpHash::of($this->request->ip()) : null,
            'user_agent' => $userAgent === null || $userAgent === '' ? null : Str::limit($userAgent, self::USER_AGENT_LENGTH, ''),
            'request_id' => is_string($requestId) ? $requestId : null,
        ]);
    }
}
