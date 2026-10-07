<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\CocIntegration\Data\CocKeyData;
use App\Domain\CocIntegration\Services\CocApiHealthReport;
use App\Domain\Media\Services\MediaProcessingStats;
use App\Domain\Operations\Data\FailedJobTarget;
use App\Domain\Operations\Data\QueueStatData;
use App\Domain\Operations\Queries\FailedJobsQuery;
use App\Domain\Operations\Queries\QueueStatsQuery;
use App\Domain\Operations\Queries\SchedulerStatusQuery;
use App\Http\Controllers\Controller;
use App\Support\Seo\PageMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * System Health (specs/20 §6, NFR-OBS-6): queues with media processing time (P3-02), failed jobs
 * by class, the scheduler and the Clash of Clans key pool, so staff can tell a lying user from a
 * broken sync. Retry and delete post to FailedJobController (P2-19); one class's jobs load on
 * demand. Each panel is its own deferred group, and nothing is cached (specs/21 §3).
 */
class SystemHealthController extends Controller
{
    public function __invoke(Request $request, QueueStatsQuery $queues, FailedJobsQuery $failedJobs, SchedulerStatusQuery $scheduler, CocApiHealthReport $cocApi, MediaProcessingStats $media): Response
    {
        Gate::authorize(StaffAbility::AccessAdmin->value);
        Gate::authorize(StaffAbility::ViewPlatformStats->value);

        return PageMeta::page('Admin/System', [
            'canManageFailedJobs' => Gate::allows(StaffAbility::ManageFailedJobs->value),
            'failedJobsBulkMax' => (int) config('platform.admin.failed_jobs_bulk_max'),
            // One class's jobs, loaded on demand with `jobsClass` or `jobsUnreadable` (P2-19).
            'failedJobList' => Inertia::optional(fn (): array => $failedJobs->jobs($request->boolean('jobsUnreadable')
                ? FailedJobTarget::unreadable()
                : FailedJobTarget::ofClass($request->string('jobsClass')->limit(255, '')->toString()))->toArray()),
            'queues' => Inertia::defer(fn (): array => array_map(fn (QueueStatData $q): array => $q->toArray(), $queues->stats()), 'queues'),
            'mediaProcessing' => Inertia::defer(fn (): array => $media->summary()->toArray(), 'queues'),
            'failedJobs' => Inertia::defer(fn (): array => $failedJobs->byClass()->toArray(), 'failedJobs'),
            'scheduler' => Inertia::defer(fn (): array => $scheduler->status()->toArray(), 'scheduler'),
            'cocApiHealth' => Inertia::defer(fn (): array => $cocApi->summary()->toArray(), 'cocApi'),
            'cocKeys' => Inertia::defer(fn (): array => array_map(fn (CocKeyData $k): array => $k->toArray(), $cocApi->keys()), 'cocApi'),
        ], new PageMeta(title: 'System health', noindex: true));
    }
}
