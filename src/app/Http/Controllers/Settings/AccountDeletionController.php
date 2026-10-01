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
    public function edit(Request $request): Response
    {
        $user = $this->user($request);
        Gate::authorize('viewDangerZone', $user);

        return PageMeta::page('Settings/DangerZone', (new DangerZonePageData(
            graceDays: (int) config('platform.auth.deletion_grace_days'),
            canRequestDeletion: Gate::allows('requestDeletion', $user),
        ))->toArray(), new PageMeta(title: 'Danger zone', noindex: true));
    }

    public function destroy(RequestAccountDeletionRequest $request, AccountDeletionService $deletion): RedirectResponse
    {
        $deletion->request($this->user($request), (string) $request->validated('current_password'));
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget('remember_since'));

        return redirect()->route('login', status: 303)->with('status',
            'Deletion requested. Your profile is hidden and every device was signed out. Sign in within '.config('platform.auth.deletion_grace_days').' days to cancel.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
