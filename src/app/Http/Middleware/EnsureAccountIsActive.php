<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Services\UserStatusService;
use App\Http\Data\Account\AccountStatusPageData;
use App\Models\User;
use App\Support\Observability\PermissionDenialLog;
use App\Support\Seo\PageMeta;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Write gate by account status (specs/04 §3 #2). `account.active` blocks suspended, banned and
 * pending-deletion accounts; `account.active:content` also blocks restricted ones, for uploads,
 * publishing, commenting, applying and messaging. Reads pass, so the gate can sit on a whole
 * route group. Defence in depth: policies check too.
 */
class EnsureAccountIsActive
{
    public function __construct(private readonly UserStatusService $statuses) {}

    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $request->isMethodSafe()) {
            return $next($request);
        }

        $allowed = $scope === 'content' ? $user->allowsContentWrites() : $user->allowsAccountWrites();

        if ($allowed) {
            return $next($request);
        }

        PermissionDenialLog::record($request, $user->ulid, ['reason' => 'status:'.$this->statuses->effectiveStatus($user)->value]);

        if ($request->expectsJson() || $request->is('uploads/*')) {
            return response()->json(['message' => __('account.write_blocked')], 403);
        }

        return PageMeta::page(
            'Account/WriteBlocked',
            AccountStatusPageData::fromNotice($this->statuses->notice($user))->toArray(),
            new PageMeta(title: 'Not available right now', noindex: true),
        )->toResponse($request)->setStatusCode(403);
    }
}
