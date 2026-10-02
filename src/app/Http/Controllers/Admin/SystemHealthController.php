<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\CocIntegration\Data\CocKeyData;
use App\Domain\CocIntegration\Services\CocApiHealthReport;
use App\Domain\Operations\Data\QueueStatData;
use App\Domain\Operations\Queries\FailedJobsQuery;
use App\Domain\Operations\Queries\QueueStatsQuery;
use App\Domain\Operations\Queries\SchedulerStatusQuery;
use App\Http\Controllers\Controller;
use App\Support\Seo\PageMeta;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * System Health (specs/20 §6, NFR-OBS-6): queues, failed jobs by class, the scheduler and the
 * Clash of Clans key pool, so staff can tell a lying user from a broken sync. Read-only; retry and
 * delete join with P2-19. Each panel is its own deferred group, and nothing is cached
 * (specs/21 §3).
 */
class SystemHealthController extends Controller
{
    public function __invoke(QueueStatsQuery $queues, FailedJobsQuery $failedJobs, SchedulerStatusQuery $scheduler, CocApiHealthReport $cocApi): Response
    {
        Gate::authorize(StaffAbility::AccessAdmin->value);
        Gate::authorize(StaffAbility::ViewPlatformStats->value);

        return PageMeta::page('Admin/System', [
            'queues' => Inertia::defer(fn (): array => array_map(fn (QueueStatData $q): array => $q->toArray(), $queues->stats()), 'queues'),
            'failedJobs' => Inertia::defer(fn (): array => $failedJobs->byClass()->toArray(), 'failedJobs'),
            'scheduler' => Inertia::defer(fn (): array => $scheduler->status()->toArray(), 'scheduler'),
            'cocApiHealth' => Inertia::defer(fn (): array => $cocApi->summary()->toArray(), 'cocApi'),
            'cocKeys' => Inertia::defer(fn (): array => array_map(fn (CocKeyData $k): array => $k->toArray(), $cocApi->keys()), 'cocApi'),
        ], new PageMeta(title: 'System health', noindex: true));
    }
}
