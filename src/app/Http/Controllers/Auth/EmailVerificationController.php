<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Enums\EmailVerificationOutcome;
use App\Domain\Auth\Services\EmailVerificationService;
use App\Http\Controllers\Controller;
use App\Http\Data\Auth\VerificationResultPageData;
use App\Http\Data\Auth\VerifyEmailPageData;
use App\Models\User;
use App\Support\Privacy\EmailMask;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Email verification (FR-AUTH-3). The link opens a page naming the account; its button confirms
 * (a POST, so link-scanning mail gateways confirm nothing). It works signed in or not and never
 * signs anyone in. Signatures are checked here rather than by the `signed` middleware, so a stale
 * link gets a page, not a 403.
 */
class EmailVerificationController extends Controller
{
    private const RESULT = 'verification_result';

    public function notice(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        $status = $request->session()->get('status');

        return PageMeta::page('Auth/VerifyEmail', (new VerifyEmailPageData(
            email: EmailMask::of($user->email),
            status: is_string($status) ? $status : null,
            linkMinutes: (int) config('platform.auth.verification_link_minutes'),
        ))->toArray(), new PageMeta(title: 'Confirm your email', noindex: true));
    }

    public function show(Request $request, string $ulid, string $hash, EmailVerificationService $verifications): Response
    {
        if (! $request->hasValidSignature()) {
            return $this->page($request, EmailVerificationOutcome::Invalid, $ulid, null, null);
        }

        $link = $verifications->inspect($ulid, $hash);
        $pending = $link->outcome === EmailVerificationOutcome::Pending;

        return $this->page($request, $link->outcome, $ulid, $pending ? $link->username : null, $pending ? $request->fullUrl() : null);
    }

    public function confirm(Request $request, string $ulid, string $hash, EmailVerificationService $verifications): RedirectResponse
    {
        $viewer = $request->user();

        $outcome = $request->hasValidSignature()
            ? $verifications->confirm($ulid, $hash, $request->ip(), $viewer instanceof User ? $viewer : null, $request->session()->getId())
            : EmailVerificationOutcome::Invalid;

        return redirect()->route('verification.result')->with(self::RESULT, ['outcome' => $outcome->value, 'ulid' => strtolower($ulid)]);
    }

    public function result(Request $request): Response|RedirectResponse
    {
        $result = $request->session()->get(self::RESULT);
        $outcome = is_array($result) ? EmailVerificationOutcome::tryFrom((string) ($result['outcome'] ?? '')) : null;

        if ($outcome === null) {
            return redirect()->route('home');
        }

        return $this->page($request, $outcome, (string) ($result['ulid'] ?? ''), null, null);
    }

    public function send(Request $request, EmailVerificationService $verifications): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if (! $verifications->resend($user)) {
            return redirect()->route('home');
        }

        $minutes = (int) config('platform.auth.verification_link_minutes');

        return back()->with('status', "A new link is on its way. It expires in {$minutes} minutes.");
    }

    private function page(Request $request, EmailVerificationOutcome $outcome, string $ulid, ?string $username, ?string $confirmUrl): Response
    {
        $viewer = $request->user();

        return PageMeta::page('Auth/VerificationResult', (new VerificationResultPageData(
            outcome: $outcome->value,
            message: $outcome->label(),
            signedIn: $viewer instanceof User && $viewer->ulid === strtolower($ulid),
            username: $username,
            confirmUrl: $confirmUrl,
        ))->toArray(), new PageMeta(title: 'Confirm your email', noindex: true));
    }
}
