<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Auth\Enums\EmailChangeOutcome;
use App\Domain\Auth\Services\EmailChangeService;
use App\Http\Controllers\Controller;
use App\Http\Data\Settings\EmailChangeConfirmPageData;
use App\Http\Requests\Settings\ChangeEmailRequest;
use App\Http\Requests\Settings\ResendEmailChangeRequest;
use App\Models\User;
use App\Support\Privacy\EmailMask;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Email change (FR-AUTH-8). A request takes the current password inline and answers the same for
 * every address; the link goes to the
 * new address and opens a page whose button (a POST) confirms, for the account signed in on that
 * browser only. Signatures are checked here rather than by the `signed` middleware, so a stale
 * link gets a page, not a 403.
 */
class EmailChangeController extends Controller
{
    private const RESULT = 'email_change_result';

    public function update(ChangeEmailRequest $request, EmailChangeService $changes): RedirectResponse
    {
        $email = $request->string('email')->toString();
        $changes->request($this->user($request), $email, $request->string('current_password')->toString(), $request->ip());
        // Typing the current password counts as confirming it (specs/11, 15-minute window).
        $request->session()->passwordConfirmed();

        return back()->with('success', $this->sent($email));
    }

    public function resend(ResendEmailChangeRequest $request, EmailChangeService $changes): RedirectResponse
    {
        $user = $this->user($request);
        $pending = $user->pending_email;

        if ($pending === null || ! $changes->resend($user, $request->string('current_password')->toString(), $request->ip())) {
            return back();
        }

        $request->session()->passwordConfirmed();

        return back()->with('success', $this->sent($pending));
    }

    public function destroy(Request $request, EmailChangeService $changes): RedirectResponse
    {
        $changes->cancel($this->user($request));

        return back()->with('success', 'Email change cancelled. Your email has not changed.');
    }

    public function show(Request $request, string $ulid, string $hash, EmailChangeService $changes): Response
    {
        if (! $request->hasValidSignature()) {
            return $this->page(EmailChangeOutcome::Invalid);
        }

        $link = $changes->inspect($ulid, $hash, $this->user($request));

        if ($link->outcome !== EmailChangeOutcome::Pending) {
            return $this->page($link->outcome);
        }

        return $this->page($link->outcome, $link->username, $link->newEmail, $request->fullUrl());
    }

    public function confirm(Request $request, string $ulid, string $hash, EmailChangeService $changes): RedirectResponse
    {
        $outcome = $request->hasValidSignature()
            ? $changes->confirm($ulid, $hash, $this->user($request), $request->session()->getId(), $request->ip())
            : EmailChangeOutcome::Invalid;

        if ($outcome === EmailChangeOutcome::Changed) {
            // A new session id, so a stolen copy of this browser's cookie dies too (specs/04 §4).
            $request->session()->migrate(true);
        }

        return redirect()->route('settings.email.result')->with(self::RESULT, $outcome->value);
    }

    public function result(Request $request): Response|RedirectResponse
    {
        $outcome = EmailChangeOutcome::tryFrom((string) $request->session()->get(self::RESULT, ''));

        if ($outcome === null || $outcome === EmailChangeOutcome::Pending) {
            return redirect()->route('settings.security.edit');
        }

        return $this->page($outcome);
    }

    private function sent(string $email): string
    {
        $minutes = (int) config('platform.auth.verification_link_minutes');

        return 'Check '.EmailMask::of($email)." for a link to confirm the change. It expires in {$minutes} minutes.";
    }

    private function page(EmailChangeOutcome $outcome, ?string $username = null, ?string $newEmail = null, ?string $confirmUrl = null): Response
    {
        return PageMeta::page('Settings/EmailChangeConfirm', (new EmailChangeConfirmPageData(
            outcome: $outcome->value,
            message: $outcome->label(),
            username: $username,
            newEmail: $newEmail,
            confirmUrl: $confirmUrl,
        ))->toArray(), new PageMeta(title: 'Confirm your new email', noindex: true));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
