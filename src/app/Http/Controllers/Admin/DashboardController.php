<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Enums\StaffAbility;
use App\Http\Controllers\Controller;
use App\Support\Seo\PageMeta;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

/**
 * The admin landing page. Its panels (open reports, disputes, sign-ups, FR-ADMIN-5) arrive with
 * the admin dashboard (P1-13).
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize(StaffAbility::AccessAdmin->value);

        return PageMeta::page('Admin/Dashboard', meta: new PageMeta(title: 'Admin', noindex: true));
    }
}
