<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Auth\Services\PasswordChangeService;
use App\Domain\Auth\Services\SessionService;
use App\Http\Controllers\Controller;
use App\Http\Data\Settings\SecuritySettingsPageData;
use App\Http\Requests\Settings\ChangePasswordRequest;
use App\Models\User;
use App\Support\Privacy\EmailMask;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Password, email and signed-in sessions (FR-AUTH-7/8, specs/04 §4). The email change has its own
 * controller; authorization runs in the services.
 */
class SecurityController extends Controller
{
    public function edit(Request $request, SessionService $sessions): Response
    {
        $user = $this->user($request);
        $currentId = $request->session()->getId();

        $page = new SecuritySettingsPageData(
            passwordMinLength: (int) config('platform.auth.min_password_length'),
            email: EmailMask::of($user->email),
            pendingEmail: $user->pending_email === null ? null : EmailMask::of($user->pending_email),
            linkMinutes: (int) config('platform.auth.verification_link_minutes'),
        );

        return PageMeta::page('Settings/Security', [
            ...$page->toArray(),
            'sessions' => Inertia::defer(fn (): array => array_map(fn ($session) => $session->toArray(), $sessions->listFor($user, $currentId))),
        ], new PageMeta(title: 'Security settings', noindex: true));
    }

    public function updatePassword(ChangePasswordRequest $request, PasswordChangeService $passwords): RedirectResponse
    {
        $passwords->change(
            $this->user($request),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
            $request->session()->getId(),
        );

        // A new session id, so a stolen copy of this browser's cookie dies too (specs/04 §4).
        $request->session()->migrate(true);
        // Typing the current password counts as confirming it (specs/11, 15-minute window).
        $request->session()->passwordConfirmed();

        return back()->with('success', 'Password changed. Every other device was signed out.');
    }

    public function destroySession(Request $request, string $key, SessionService $sessions): RedirectResponse
    {
        $sessions->revoke($this->user($request), $key, $request->session()->getId());

        return back()->with('success', 'That device was signed out.');
    }

    public function destroyOtherSessions(Request $request, SessionService $sessions): RedirectResponse
    {
        $count = $sessions->revokeOthers($this->user($request), $request->session()->getId());
        // A copy of this browser's cookie is another device too.
        $request->session()->migrate(true);

        return back()->with('success', $count === 0 ? 'No other devices were signed in.' : 'Every other device was signed out.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
