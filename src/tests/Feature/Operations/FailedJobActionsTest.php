<?php

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Operations\Data\FailedJobTarget;
use App\Domain\Operations\Services\FailedJobService;
use App\Domain\PlayerAccounts\Jobs\RestoreFeaturedAccountJob;
use App\Models\User;
use Illuminate\Queue\Events\JobRetryRequested;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

// P2-19: retry and delete failed jobs from System Health (specs/20 §5, §4 rule 7), each job audited
// (specs/12 §9, NFR-SEC-6).

beforeEach(function () {
    Date::setTestNow('2026-10-06 12:00:00');
    $this->admin = User::factory()->admin()->create();
});

/**
 * A real failed job: its payload is what the database queue wrote, so `queue:retry` can load it.
 */
function realFailedJob(int $userId = 1, string $queue = 'default'): string
{
    Queue::connection('database')->pushOn($queue, new RestoreFeaturedAccountJob($userId));
    $job = DB::table('jobs')->orderByDesc('id')->first();
    DB::table('jobs')->where('id', $job->id)->delete();
    $uuid = (string) Str::uuid();
    DB::table('failed_jobs')->insert([
        'uuid' => $uuid, 'connection' => 'database', 'queue' => $queue,
        'payload' => $job->payload, 'exception' => 'RuntimeException: secret@example.com broke', 'failed_at' => now()->subMinutes(5),
    ]);

    return $uuid;
}

function unreadableFailedJob(string $payload = 'not json'): string
{
    $uuid = (string) Str::uuid();
    DB::table('failed_jobs')->insert([
        'uuid' => $uuid, 'connection' => 'database', 'queue' => 'default',
        'payload' => $payload, 'exception' => 'boom', 'failed_at' => now()->subMinutes(5),
    ]);

    return $uuid;
}

const RESTORE_JOB = RestoreFeaturedAccountJob::class;

it('retries one job: back on its queue with attempts reset, gone from failed jobs, audited', function () {
    $uuid = realFailedJob(queue: 'low');
    $id = DB::table('failed_jobs')->where('uuid', $uuid)->value('id');

    $this->actingAs($this->admin)->from('/admin/system')->post('/admin/system/failed-jobs/retry', ['uuid' => $uuid])
        ->assertRedirect('/admin/system')->assertSessionHas('success', 'Retried 1 job.');

    $queued = DB::table('jobs')->sole();
    expect($queued->queue)->toBe('low')
        ->and(json_decode($queued->payload, true)['displayName'])->toBe(RESTORE_JOB)
        ->and((int) $queued->attempts)->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);

    $entry = AuditLog::query()->sole();
    expect($entry->action)->toBe(AuditAction::FailedJobRetried)
        ->and($entry->auditable_type)->toBe(AuditSubject::FailedJob)
        ->and($entry->auditable_id)->toBe((int) $id)
        ->and($entry->actor_id)->toBe($this->admin->id)
        ->and($entry->context)->toEqual(['class' => RESTORE_JOB, 'queue' => 'low', 'uuid' => $uuid, 'batch' => 1]);
});

it('deletes one job and audits it, without the payload or the exception', function () {
    $uuid = realFailedJob();

    $this->actingAs($this->admin)->from('/admin/system')->delete('/admin/system/failed-jobs', ['uuid' => $uuid])
        ->assertSessionHas('success', 'Deleted 1 job.');

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);
    $entry = AuditLog::query()->sole();
    expect($entry->action)->toBe(AuditAction::FailedJobDeleted)
        ->and(json_encode($entry->toArray()))->not->toContain('secret@example.com')->not->toContain('RuntimeException');
});

it('acts on a whole class, oldest first, capped per action, and says how many are left', function () {
    config(['platform.admin.failed_jobs_bulk_max' => 2]);
    $uuids = [realFailedJob(1), realFailedJob(2), realFailedJob(3)];
    unreadableFailedJob();

    $this->actingAs($this->admin)->from('/admin/system')->post('/admin/system/failed-jobs/retry', ['class' => RESTORE_JOB])
        ->assertSessionHas('success', 'Retried 2 jobs. 1 job left; run it again for the rest.');

    expect(DB::table('failed_jobs')->pluck('uuid')->all())->toEqualCanonicalizing([$uuids[2], DB::table('failed_jobs')->where('payload', 'not json')->value('uuid')])
        ->and(DB::table('jobs')->count())->toBe(2)
        ->and(AuditLog::query()->pluck('context')->map(fn (array $c) => [$c['uuid'], $c['batch']])->all())->toBe([[$uuids[0], 2], [$uuids[1], 2]]);
});

it('deletes jobs with an unreadable payload, and never retries them', function () {
    unreadableFailedJob();
    unreadableFailedJob('{"displayName": ""}');
    realFailedJob();

    $this->actingAs($this->admin)->from('/admin/system')->post('/admin/system/failed-jobs/retry', ['unreadable' => true])
        ->assertSessionHasErrors(['target' => 'Jobs with an unreadable payload can only be deleted.']);
    $this->actingAs($this->admin)->from('/admin/system')->delete('/admin/system/failed-jobs', ['unreadable' => true])
        ->assertSessionHas('success', 'Deleted 2 jobs.');

    expect(DB::table('failed_jobs')->count())->toBe(1)
        ->and(AuditLog::query()->pluck('context')->pluck('class')->all())->toBe([null, null]);
});

it('keeps a job whose command no longer loads, for deletion', function () {
    $payload = json_encode(['displayName' => RESTORE_JOB, 'data' => ['commandName' => RESTORE_JOB, 'command' => 'O:99:"App\\Gone":0:{}']]);
    $uuid = unreadableFailedJob((string) $payload);

    $this->actingAs($this->admin)->from('/admin/system')->post('/admin/system/failed-jobs/retry', ['uuid' => $uuid])
        ->assertSessionHas('success', 'Retried 0 jobs. 1 job could not be retried and can only be deleted.');

    expect(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('skips a job another admin handles while this run is going, and refuses a target with nothing left', function () {
    $first = realFailedJob();
    $other = realFailedJob(2);
    $service = app(FailedJobService::class);
    // The other admin removes the second job after this run picked it and before it locks it.
    Event::listen(JobRetryRequested::class, function () use ($other): void {
        DB::table('failed_jobs')->where('uuid', $other)->delete();
    });

    $result = $service->retry($this->admin, FailedJobTarget::ofClass(RESTORE_JOB));

    expect([$result->done, $result->skipped, $result->kept, $result->left])->toBe([1, 1, 0, 0])
        ->and(AuditLog::query()->sole()->context['uuid'])->toBe($first);
    expect(fn () => $service->delete($this->admin, FailedJobTarget::job($first)))->toThrow(ValidationException::class, 'no longer in the failed list');
    $this->actingAs($this->admin)->from('/admin/system')->post('/admin/system/failed-jobs/retry', ['class' => 'App\\Jobs\\Unknown'])
        ->assertSessionHasErrors(['target' => 'There are no failed jobs of that kind any more.']);
});

it('steps past jobs it cannot retry, so they never block the rest of their class', function () {
    config(['platform.admin.failed_jobs_bulk_max' => 2]);
    $broken = (string) json_encode(['displayName' => RESTORE_JOB, 'data' => ['commandName' => RESTORE_JOB, 'command' => 'O:99:"App\\Gone":0:{}']]);
    foreach (range(1, 3) as $ignored) {
        unreadableFailedJob($broken);
    }
    realFailedJob(1);
    realFailedJob(2);

    $this->actingAs($this->admin)->from('/admin/system')->post('/admin/system/failed-jobs/retry', ['class' => RESTORE_JOB])
        ->assertSessionHas('success', 'Retried 2 jobs. 3 jobs could not be retried and can only be deleted.');

    expect(DB::table('jobs')->count())->toBe(2)
        ->and(DB::table('failed_jobs')->count())->toBe(3);
});

it('validates that exactly one target is given', function (array $input) {
    realFailedJob();

    $this->actingAs($this->admin)->from('/admin/system')->post('/admin/system/failed-jobs/retry', $input)->assertSessionHasErrors();
    expect(DB::table('failed_jobs')->count())->toBe(1);
})->with([
    'nothing' => [[]],
    'two targets' => [['class' => RESTORE_JOB, 'unreadable' => true]],
    'not a uuid' => [['uuid' => '../../etc/passwd']],
    'class too long' => [['class' => str_repeat('a', 256)]],
]);

it('lists one class of jobs on demand, newest first, capped, and gives the can flag', function () {
    config(['platform.admin.failed_jobs_list_max' => 2]);
    $first = realFailedJob();
    Date::setTestNow(now()->addMinute());
    $second = realFailedJob(2);
    Date::setTestNow(now()->addMinute());
    $third = realFailedJob(3);

    $this->actingAs($this->admin)->get('/admin/system')
        ->assertInertia(fn (Assert $page) => $page->component('Admin/System')
            ->where('canManageFailedJobs', true)
            ->where('failedJobsBulkMax', config('platform.admin.failed_jobs_bulk_max'))
            ->missing('failedJobList')
            ->reloadOnly('failedJobList', fn (Assert $reload) => $reload->where('failedJobList.total', 0)));

    $this->actingAs($this->admin)->get('/admin/system?jobsClass='.urlencode(RESTORE_JOB))
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('failedJobList', fn (Assert $reload) => $reload
            ->where('failedJobList.name', RESTORE_JOB)
            ->where('failedJobList.total', 3)
            ->where('failedJobList.jobs.0.uuid', $third)
            ->where('failedJobList.jobs.1.uuid', $second)
            ->has('failedJobList.jobs', 2)
            ->has('failedJobList.jobs.0', 3)));
});

it('matches a class by the trimmed name the page shows', function () {
    $uuid = unreadableFailedJob((string) json_encode(['displayName' => '  App\\Jobs\\Padded ']));

    $this->actingAs($this->admin)->from('/admin/system')->delete('/admin/system/failed-jobs', ['class' => 'App\\Jobs\\Padded'])
        ->assertSessionHas('success', 'Deleted 1 job.');
    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse();
});

it('reads its limits from config', function () {
    expect(config('platform.admin'))->toMatchArray(['failed_jobs_bulk_max' => 200, 'failed_jobs_list_max' => 50])
        ->and(config('platform.rate_limits.admin_failed_jobs_per_minute'))->toBe(20);
});
