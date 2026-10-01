<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Data\AuditLogFilterData;
use App\Domain\Audit\Queries\AuditLogQuery;
use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Queries\AdminUserQuery;
use App\Domain\Users\Queries\ProfileReadModel;
use App\Http\Controllers\Controller;
use App\Http\Data\Admin\AdminUserFiltersData;
use App\Http\Data\Admin\AdminUserIndexPageData;
use App\Http\Data\Admin\AdminUserListData;
use App\Http\Data\Admin\AdminUserShowPageData;
use App\Http\Data\Admin\AuditTrailEntryData;
use App\Http\Data\Admin\FilterOptionData;
use App\Http\Requests\Admin\AdminUserFilterRequest;
use App\Models\User;
use App\Support\Privacy\IpHash;
use App\Support\Seo\PageMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin user list and detail (FR-ADMIN-2 read side, FR-ADMIN-6: support from data). Admin
 * and above (`view-users`): the pages show email (specs/11 §5).
 */
class UserController extends Controller
{
    public function index(AdminUserFilterRequest $request, AdminUserQuery $users): Response
    {
        Gate::authorize(StaffAbility::ViewUsers->value);

        $viewer = $request->user();
        abort_unless($viewer instanceof User, 401);
        $filters = $request->filters();
        $cursor = $request->cursor();

        $page = new AdminUserIndexPageData(
            filters: new AdminUserFiltersData(
                search: $filters->search,
                role: $filters->role?->value,
                status: $filters->status?->value,
            ),
            roles: array_map(fn (Role $role) => new FilterOptionData($role->value, $role->label()), AdminUserFilterRequest::listedRoles()),
            statuses: array_map(fn (UserStatus $status) => new FilterOptionData($status->value, $status->label()), UserStatus::cases()),
        );

        return PageMeta::page('Admin/Users/Index', [
            ...$page->toArray(),
            'users' => Inertia::defer(function () use ($request, $viewer, $users, $filters, $cursor): array {
                $slice = $users->page($viewer, $filters, (int) config('platform.admin.per_page'), $cursor);

                // specs/11 §3 "admin data access": the rows carry emails. Logged where they load, not
                // on the page shell. The search text stays out, since it may itself be an email.
                Log::channel('security')->info('admin.users_listed', [
                    'actor' => $viewer->ulid,
                    'searched' => $filters->search !== null,
                    'role' => $filters->role?->value,
                    'status' => $filters->status?->value,
                    'paged' => $cursor !== null,
                    'rows' => count($slice->entries),
                    'ip_hash' => IpHash::of($request->ip()),
                ]);

                return AdminUserListData::fromSlice($slice)->toArray();
            }),
        ], new PageMeta(title: 'Users', noindex: true));
    }

    public function show(Request $request, string $ulid, AdminUserQuery $users, ProfileReadModel $profiles, AuditLogQuery $audit): Response
    {
        Gate::authorize(StaffAbility::ViewUsers->value);

        $viewer = $request->user();
        abort_unless($viewer instanceof User, 401);

        $user = $users->find($viewer, $ulid);
        abort_if($user === null, 404);

        // specs/11 §3 "admin data access": the page shows the account's email.
        Log::channel('security')->info('admin.user_viewed', [
            'actor' => $viewer->ulid,
            'user' => $user->ulid,
            'ip_hash' => IpHash::of($request->ip()),
        ]);

        $trail = $audit->page(new AuditLogFilterData(subjectId: $user->id), (int) config('platform.admin.audit_trail_limit'));

        $page = new AdminUserShowPageData(
            user: $users->detail($user),
            displayName: $profiles->displayNameOf($user),
            avatarUrl: $profiles->avatarUrl($user),
            auditTrail: array_map(AuditTrailEntryData::fromRecord(...), $trail->entries),
            moreAuditEntries: $trail->olderCursor !== null,
        );

        return PageMeta::page('Admin/Users/Show', $page->toArray(), new PageMeta(title: $user->username, noindex: true));
    }
}
