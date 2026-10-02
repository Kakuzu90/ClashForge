<?php

namespace App\Http\Controllers\Moderation;

use App\Domain\Auth\Enums\StaffAbility;
use App\Http\Controllers\Controller;
use App\Support\Seo\PageMeta;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

/**
 * The report queue for moderators (specs/12). Reports arrive with Moderation v1 (P3-06); until
 * then the page is its empty state.
 */
class ReportController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(StaffAbility::ViewReportQueue->value);

        return PageMeta::page('Moderation/Reports', [], new PageMeta(title: 'Reports', noindex: true));
    }
}
