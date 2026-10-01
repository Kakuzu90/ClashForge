<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Data\UsernameFieldRules;
use App\Domain\Auth\Services\RegistrationGuard;
use App\Domain\Auth\Services\RegistrationService;
use App\Http\Controllers\Controller;
use App\Http\Data\Auth\RegisterPageData;
use App\Http\Data\Auth\RegisterSentPageData;
use App\Http\Requests\Auth\RegisterRequest;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * Registration (FR-AUTH-1/3/11). Every accepted submission ends on the same page, whether the
 * email was new, already had an account, or tripped a bot trap; nobody is signed in.
 */
class RegisterController extends Controller
{
    public function create(): Response
    {
        $page = new RegisterPageData(
            usernameMin: UsernameFieldRules::MIN,
            usernameMax: UsernameFieldRules::MAX,
            passwordMin: (int) config('platform.auth.min_password_length'),
            turnstileSiteKey: config('services.turnstile.site_key'),
            formStarted: RegistrationGuard::startToken(),
        );

        return PageMeta::page('Auth/Register', $page->toArray(), new PageMeta(title: 'Create your account', noindex: true));
    }

    public function store(RegisterRequest $request, RegistrationService $registrations): RedirectResponse
    {
        $registrations->submit(
            $request->registration(),
            $request->input(RegistrationGuard::HONEYPOT),
            $request->input(RegistrationGuard::STARTED),
            $request->ip(),
        );

        return redirect()->route('register.sent');
    }

    public function sent(): Response
    {
        return PageMeta::page(
            'Auth/RegisterSent',
            (new RegisterSentPageData(linkMinutes: (int) config('platform.auth.verification_link_minutes')))->toArray(),
            new PageMeta(title: 'Check your email', noindex: true),
        );
    }
}
