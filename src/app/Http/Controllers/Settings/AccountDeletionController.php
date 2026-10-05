<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Auth\Services\AccountDeletionService;
use App\Http\Controllers\Controller;
use App\Http\Data\Settings\DangerZonePageData;
use App\Http\Requests\Settings\RequestAccountDeletionRequest;
use App\Models\User;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class AccountDeletionController extends Controller
{
    public function edit(Request $request, AccountDeletionService $deletion): Response
    {
        $user = $this->user($request);
        Gate::authorize('viewDangerZone', $user);

        return PageMeta::page('Settings/DangerZone', (new DangerZonePageData(
            graceDays: (int) config('platform.auth.deletion_grace_days'),
            canRequestDeletion: Gate::allows('requestDeletion', $user),
            holds: $deletion->holds($user->id),
        ))->toArray(), new PageMeta(title: 'Danger zone', noindex: true));
    }

    public function destroy(RequestAccountDeletionRequest $request, AccountDeletionService $deletion): RedirectResponse
    {
        $user = $this->user($request);
        $deletion->request($user, (string) $request->validated('current_password'));
        $held = $deletion->holds($user->id) !== [];
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget('remember_since'));

        $days = config('platform.auth.deletion_grace_days');

        return redirect()->route('login', status: 303)->with('status', $held
            ? "Deletion requested. Your profile is hidden and every device was signed out. An ownership dispute is still open, so your account is deleted once it is resolved, and no sooner than {$days} days. Sign in before then to cancel."
            : "Deletion requested. Your profile is hidden and every device was signed out. Sign in within {$days} days to cancel.");
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
