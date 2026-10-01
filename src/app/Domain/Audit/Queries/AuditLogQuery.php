<?php

namespace App\Domain\Audit\Queries;

use App\Domain\Audit\Data\AuditLogFilterData;
use App\Domain\Audit\Data\AuditLogRecordData;
use App\Domain\Audit\Data\AuditLogSliceData;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\Cursor;

/**
 * The audit log viewer's read model (FR-ADMIN-4). Joins `users` for the actor and target
 * usernames by table, not model, so Audit stays a leaf (specs/05 §1 read-model escape hatch).
 * Ordered by id, which follows insertion order, so the cursor needs one column.
 */
class AuditLogQuery
{
    private const CURSOR_COLUMN = 'audit_logs.id';

    /**
     * Whether a cursor is one this query issued in shape: base64url JSON with the direction flag
     * and an integer id. Anything else would make the paginator throw.
     */
    public static function acceptsCursor(string $cursor): bool
    {
        $json = base64_decode(str_replace(['-', '_'], ['+', '/'], $cursor), true);
        $parameters = $json === false ? null : json_decode($json, true);

        return is_array($parameters)
            && is_bool($parameters['_pointsToNextItems'] ?? null)
            && is_int($parameters[self::CURSOR_COLUMN] ?? null);
    }

    public function page(AuditLogFilterData $filters, int $perPage, ?string $cursor = null): AuditLogSliceData
    {
        $query = AuditLog::query()
            ->leftJoin('users as actor', 'actor.id', '=', 'audit_logs.actor_id')
            ->leftJoin('users as target', function ($join): void {
                $join->on('target.id', '=', 'audit_logs.auditable_id')
                    ->where('audit_logs.auditable_type', '=', AuditSubject::User->value);
            })
            ->select('audit_logs.*', 'actor.username as actor_username', 'target.username as target_username')
            ->orderByDesc(self::CURSOR_COLUMN);

        $this->filter($query, $filters);

        $page = $query->cursorPaginate($perPage, cursor: Cursor::fromEncoded($cursor));

        $entries = [];
        foreach ($page->items() as $row) {
            /** @var AuditLog $row */
            $entries[] = new AuditLogRecordData(
                id: $row->id,
                action: $row->action,
                actorId: $row->actor_id,
                actorUsername: $this->string($row->getAttribute('actor_username')),
                actorRole: $row->actor_role,
                subject: $row->auditable_type,
                subjectId: $row->auditable_id,
                subjectUsername: $this->string($row->getAttribute('target_username')),
                before: $row->before,
                after: $row->after,
                context: $row->context,
                userAgent: $row->user_agent,
                requestId: $row->request_id,
                createdAt: $row->created_at,
            );
        }

        return new AuditLogSliceData(
            entries: $entries,
            newerCursor: $page->previousCursor()?->encode(),
            olderCursor: $page->nextCursor()?->encode(),
        );
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    private function filter(Builder $query, AuditLogFilterData $filters): void
    {
        if ($filters->actor !== null) {
            $query->whereIn('audit_logs.actor_id', $this->userIds($filters->actor));
        }

        if ($filters->target !== null) {
            $query->where('audit_logs.auditable_type', AuditSubject::User->value)
                ->whereIn('audit_logs.auditable_id', $this->userIds($filters->target));
        }

        if ($filters->action !== null) {
            $query->where('audit_logs.action', $filters->action->value);
        }

        if ($filters->from !== null) {
            $query->where('audit_logs.created_at', '>=', $filters->from->utc()->startOfDay());
        }

        if ($filters->to !== null) {
            $query->where('audit_logs.created_at', '<', $filters->to->utc()->startOfDay()->addDay());
        }
    }

    /**
     * Usernames are case-insensitive in the column itself (citext / NOCASE).
     *
     * @return \Closure(QueryBuilder): void
     */
    private function userIds(string $username): \Closure
    {
        return function (QueryBuilder $sub) use ($username): void {
            $sub->select('id')->from('users')->where('username', $username);
        };
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
