<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Queries\AuditLogQuery;
use App\Domain\Auth\Enums\StaffAbility;
use App\Http\Controllers\Controller;
use App\Http\Data\Admin\AuditLogFiltersData;
use App\Http\Data\Admin\AuditLogListData;
use App\Http\Data\Admin\AuditLogPageData;
use App\Http\Data\Admin\FilterOptionData;
use App\Http\Requests\Admin\AuditLogFilterRequest;
use App\Support\Seo\PageMeta;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The read-only audit log (FR-ADMIN-4, specs/12 §9), admin and above.
 */
class AuditLogController extends Controller
{
    public function __invoke(AuditLogFilterRequest $request, AuditLogQuery $log): Response
    {
        Gate::authorize(StaffAbility::ViewAuditLog->value);

        $filters = $request->filters();
        $cursor = $request->cursor();

        $page = new AuditLogPageData(
            filters: new AuditLogFiltersData(
                actor: $filters->actor,
                target: $filters->target,
                action: $filters->action?->value,
                from: $filters->from?->toDateString(),
                to: $filters->to?->toDateString(),
            ),
            actions: array_map(fn (AuditAction $action) => new FilterOptionData($action->value, $action->label()), AuditAction::cases()),
        );

        return PageMeta::page('Admin/AuditLog', [
            ...$page->toArray(),
            'log' => Inertia::defer(fn (): array => AuditLogListData::fromPage(
                $log->page($filters, (int) config('platform.admin.per_page'), $cursor),
            )->toArray()),
        ], new PageMeta(title: 'Audit log', noindex: true));
    }
}
