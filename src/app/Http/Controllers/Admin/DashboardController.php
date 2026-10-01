<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\Auth\Queries\SignupStatsQuery;
use App\Domain\Media\Queries\MediaStorageQuery;
use App\Http\Controllers\Controller;
use App\Http\Data\Admin\AdminDashboardPageData;
use App\Support\Health\FailedJobsSummary;
use App\Support\Seo\PageMeta;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin landing page (FR-ADMIN-5). Sign-ups, failed jobs and media storage are admin and
 * above (`view-platform-stats`); open reports, disputes and API health join with their modules.
 * Nothing here is cached (specs/21 §3).
 */
class DashboardController extends Controller
{
    public function __invoke(SignupStatsQuery $signups, FailedJobsSummary $failedJobs, MediaStorageQuery $storage): Response
    {
        Gate::authorize(StaffAbility::AccessAdmin->value);

        $platformStats = Gate::allows(StaffAbility::ViewPlatformStats->value);
        $props = (new AdminDashboardPageData(platformStats: $platformStats))->toArray();

        if ($platformStats) {
            $props['signups'] = Inertia::defer(fn (): array => $signups->stats()->toArray(), 'signups');
            $props['failedJobs'] = Inertia::defer(fn (): array => $failedJobs->summary()->toArray(), 'failedJobs');
            $props['storage'] = Inertia::defer(fn (): array => $storage->storage()->toArray(), 'storage');
        }

        return PageMeta::page('Admin/Dashboard', $props, new PageMeta(title: 'Admin', noindex: true));
    }
}
