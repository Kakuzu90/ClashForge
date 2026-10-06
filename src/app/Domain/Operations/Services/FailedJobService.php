<?php

namespace App\Domain\Operations\Services;

use App\Domain\Audit\Data\AuditActorData;
use App\Domain\Audit\Data\AuditEntryData;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\Operations\Data\FailedJobActionResultData;
use App\Domain\Operations\Data\FailedJobTarget;
use App\Domain\Operations\Exceptions\FailedJobNotRetryable;
use App\Domain\Operations\Queries\FailedJobsQuery;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use stdClass;
use Throwable;

/**
 * Retry or delete failed jobs from the System Health page (specs/20 §5, P2-19): one job or a whole
 * class, at most `platform.admin.failed_jobs_bulk_max` per action (specs/20 §4 rule 7), oldest
 * first. Each job is handled in its own transaction that locks its row first, so two admins acting
 * at once never touch a job twice: the second finds it gone and counts it as skipped. Every job
 * done writes one `audit_logs` entry with its class and queue, never its payload or exception.
 */
class FailedJobService
{
    /** How far past the cap a retry may look for jobs it can retry, in multiples of the cap. */
    private const SCAN_FACTOR = 5;

    public function __construct(
        private readonly FailedJobsQuery $failedJobs,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Puts the jobs back on their queue through Laravel's own `queue:retry` (attempts reset,
     * `retryUntil` refreshed) and removes them from `failed_jobs`. A retried job runs again, which
     * every job allows (specs/20 §4 rule 1). Jobs with an unreadable payload can only be deleted.
     */
    public function retry(User $admin, FailedJobTarget $target): FailedJobActionResultData
    {
        Gate::forUser($admin)->authorize(StaffAbility::ManageFailedJobs->value);
        if ($target->unreadable) {
            throw ValidationException::withMessages(['target' => 'Jobs with an unreadable payload can only be deleted.']);
        }

        return $this->each($admin, $target, AuditAction::FailedJobRetried, function (stdClass $row): bool {
            if (FailedJobsQuery::name($this->displayName($row->payload)) === null) {
                return false;
            }

            try {
                // Same connection as `failed_jobs` and the database queue: the push and the removal
                // commit together with the audit entry, or not at all.
                Artisan::call('queue:retry', ['id' => [(string) $row->uuid]]);
            } catch (Throwable $e) {
                // A command that no longer unserialises cannot be retried; it stays for deletion.
                throw new FailedJobNotRetryable(previous: $e);
            }

            return true;
        });
    }

    public function delete(User $admin, FailedJobTarget $target): FailedJobActionResultData
    {
        Gate::forUser($admin)->authorize(StaffAbility::ManageFailedJobs->value);

        return $this->each($admin, $target, AuditAction::FailedJobDeleted, fn (stdClass $row): bool => $this->failedJobs->targeted(FailedJobTarget::job((string) $row->uuid))->delete() === 1);
    }

    /**
     * Walks the target oldest first until `failed_jobs_bulk_max` jobs are done or skipped. Jobs
     * that cannot be retried are stepped over rather than counted, so they never block the rest
     * of their class on the next run; the walk still stops after SCAN_FACTOR times the cap.
     *
     * @param  callable(stdClass): bool  $act  true when the job was handled
     */
    private function each(User $admin, FailedJobTarget $target, AuditAction $action, callable $act): FailedJobActionResultData
    {
        $max = (int) config('platform.admin.failed_jobs_bulk_max');
        $total = $this->failedJobs->targeted($target)->count();
        if ($total === 0) {
            throw ValidationException::withMessages(['target' => $target->isJob() ? 'That job is no longer in the failed list.' : 'There are no failed jobs of that kind any more.']);
        }

        $counts = ['done' => 0, 'skipped' => 0, 'kept' => 0];
        $actor = new AuditActorData(id: $admin->id, role: $admin->role->value);
        $connection = DB::connection((string) config('queue.failed.database'));
        $batch = min($total, $max);
        $lastId = 0;
        $scanned = 0;

        while ($max > $counts['done'] + $counts['skipped'] && $scanned < $max * self::SCAN_FACTOR) {
            $ids = $this->failedJobs->targeted($target)->where('id', '>', $lastId)->orderBy('id')
                ->limit($max - $counts['done'] - $counts['skipped'])->pluck('id')->all();
            if ($ids === []) {
                break;
            }

            foreach ($ids as $id) {
                $lastId = (int) $id;
                $scanned++;
                try {
                    $counts[$this->handle($connection, $lastId, $act, $action, $actor, $batch)]++;
                } catch (FailedJobNotRetryable) {
                    $counts['kept']++;
                }
            }
        }

        return new FailedJobActionResultData(
            done: $counts['done'],
            skipped: $counts['skipped'],
            kept: $counts['kept'],
            left: $this->failedJobs->targeted($target)->count(),
        );
    }

    /**
     * One job in its own transaction, its row locked first.
     *
     * @param  callable(stdClass): bool  $act
     * @return 'done'|'skipped'|'kept'
     */
    private function handle(Connection $connection, int $id, callable $act, AuditAction $action, AuditActorData $actor, int $batch): string
    {
        return $connection->transaction(function () use ($connection, $id, $act, $action, $actor, $batch): string {
            $row = $connection->table((string) config('queue.failed.table'))
                ->where('id', $id)->lockForUpdate()->first(['id', 'uuid', 'queue', 'payload']);
            // Another admin got to it first.
            if ($row === null) {
                return 'skipped';
            }
            if (! $act($row)) {
                return 'kept';
            }

            $this->audit->record(new AuditEntryData(
                actor: $actor,
                action: $action,
                subject: AuditSubject::FailedJob,
                subjectId: (int) $row->id,
                context: [
                    'class' => FailedJobsQuery::name($this->displayName($row->payload)),
                    'queue' => (string) $row->queue,
                    'uuid' => (string) $row->uuid,
                    'batch' => $batch,
                ],
            ));

            return 'done';
        });
    }

    private function displayName(mixed $payload): mixed
    {
        $decoded = is_string($payload) ? json_decode($payload, true) : null;

        return is_array($decoded) ? ($decoded['displayName'] ?? null) : null;
    }
}
