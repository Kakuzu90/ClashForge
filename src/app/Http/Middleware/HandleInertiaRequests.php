<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\CocIntegration\Enums\CocCircuitState;
use App\Domain\CocIntegration\Services\CocApiStatus;
use App\Domain\Notifications\Queries\NotificationReadModel;
use App\Domain\Users\Queries\ProfileReadModel;
use App\Http\Data\AuthData;
use App\Http\Data\AuthUserData;
use App\Http\Data\CocApiNoticeData;
use App\Http\Data\SharedPropsData;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Skip SSR for paths listed in `inertia.ssr.except`.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(...config('inertia.ssr.except', []))) {
            config(['inertia.ssr.enabled' => false]);
        }

        return parent::handle($request, $next);
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $shared = new SharedPropsData(
            auth: new AuthData(
                user: $user === null ? null : new AuthUserData(
                    username: $user->username,
                    avatarUrl: app(ProfileReadModel::class)->avatarUrl($user),
                    emailVerified: $user->hasVerifiedEmail(),
                ),
                // Show/hide flags only; the server re-checks every action (specs/04 §3).
                can: $user === null ? [] : [
                    'accessAdmin' => Gate::forUser($user)->allows(StaffAbility::AccessAdmin->value),
                    'viewReportQueue' => Gate::forUser($user)->allows(StaffAbility::ViewReportQueue->value),
                    'viewUsers' => Gate::forUser($user)->allows(StaffAbility::ViewUsers->value),
                    'viewPlatformStats' => Gate::forUser($user)->allows(StaffAbility::ViewPlatformStats->value),
                    'viewAuditLog' => Gate::forUser($user)->allows(StaffAbility::ViewAuditLog->value),
                    'resolveDisputes' => Gate::forUser($user)->allows(StaffAbility::ResolveDisputes->value),
                ],
            ),
            flash: [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            unreadCount: $user === null ? null : app(NotificationReadModel::class)->unreadCount($user),
            features: [],
            cocApi: $this->cocApiNotice(),
        );

        return [
            ...parent::share($request),
            ...$shared->toArray(),
        ];
    }

    /**
     * Null while the API is available; half-open counts, since the next call is the probe
     * (specs/09 §7). One cache read, never an API call.
     */
    private function cocApiNotice(): ?CocApiNoticeData
    {
        $state = app(CocApiStatus::class)->state();

        return $state->state === CocCircuitState::Open && $state->reason !== null ? new CocApiNoticeData($state->reason) : null;
    }
}
