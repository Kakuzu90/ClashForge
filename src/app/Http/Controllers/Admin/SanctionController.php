<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Queries\AdminUserQuery;
use App\Domain\Moderation\Services\SanctionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BanUserRequest;
use App\Http\Requests\Admin\LiftSanctionRequest;
use App\Http\Requests\Admin\SuspendUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Suspend, ban and lift from the admin user detail (FR-ADMIN-3). An account has at most one
 * active suspension or ban, so lifting needs no sanction id. SanctionService authorizes with the
 * rank rule; an account the viewer cannot see 404s first.
 */
class SanctionController extends Controller
{
    public function suspend(SuspendUserRequest $request, string $ulid, AdminUserQuery $users, SanctionService $sanctions): RedirectResponse
    {
        return $this->act($request, $ulid, $users, fn (User $viewer, User $target) => $sanctions->suspend($viewer, $target, $request->sanction()));
    }

    public function ban(BanUserRequest $request, string $ulid, AdminUserQuery $users, SanctionService $sanctions): RedirectResponse
    {
        return $this->act($request, $ulid, $users, fn (User $viewer, User $target) => $sanctions->ban($viewer, $target, $request->sanction()));
    }

    public function lift(LiftSanctionRequest $request, string $ulid, AdminUserQuery $users, SanctionService $sanctions): RedirectResponse
    {
        $note = trim($request->string('note')->toString());

        return $this->act($request, $ulid, $users, fn (User $viewer, User $target) => $sanctions->lift($viewer, $target, $note));
    }

    /**
     * @param  callable(User, User): mixed  $action
     */
    private function act(Request $request, string $ulid, AdminUserQuery $users, callable $action): RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 401);

        $target = $users->find($viewer, $ulid);
        abort_if($target === null, 404);

        // A refusal (SanctionRefused) is a validation error: it returns with the `sanction` message.
        $action($viewer, $target);

        return back();
    }
}
