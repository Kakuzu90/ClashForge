<?php

namespace App\Providers;

use App\Domain\Auth\Services\AuthenticationService;
use App\Domain\Auth\Services\PasswordResetService;
use App\Http\Data\Auth\ForgotPasswordPageData;
use App\Http\Data\Auth\LoginPageData;
use App\Http\Data\Auth\ResetPasswordPageData;
use App\Http\Responses\Auth\PasswordResetDoneResponse;
use App\Http\Responses\Auth\PasswordResetFailedResponse;
use App\Support\Seo\PageMeta;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Inertia\Response;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse;
use Laravel\Fortify\Fortify;

/**
 * Fortify runs the auth backend; the pages, copy and routes are ours (specs/06 "Auth scaffolding").
 * Routes live in routes/web/auth.php so their limiters sit next to them.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();

        $this->app->bind(FailedPasswordResetResponse::class, PasswordResetFailedResponse::class);
        $this->app->bind(PasswordResetResponse::class, PasswordResetDoneResponse::class);
    }

    public function boot(): void
    {
        Fortify::authenticateUsing(fn (Request $request) => app(AuthenticationService::class)->attempt(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        ));

        Fortify::resetUserPasswordsUsing(PasswordResetService::class);

        Fortify::loginView(fn (Request $request): Response => PageMeta::page(
            'Auth/Login',
            (new LoginPageData(status: $this->status($request)))->toArray(),
            new PageMeta(title: 'Sign in', noindex: true),
        ));

        Fortify::requestPasswordResetLinkView(fn (Request $request): Response => PageMeta::page(
            'Auth/ForgotPassword',
            (new ForgotPasswordPageData(status: $this->status($request)))->toArray(),
            new PageMeta(title: 'Reset your password', noindex: true),
        ));

        Fortify::resetPasswordView(fn (Request $request): Response => PageMeta::page(
            'Auth/ResetPassword',
            (new ResetPasswordPageData(token: (string) $request->route('token'), email: $request->string('email')->toString()))->toArray(),
            new PageMeta(title: 'Choose a new password', noindex: true),
        ));
    }

    private function status(Request $request): ?string
    {
        $status = $request->session()->get('status');

        return is_string($status) ? $status : null;
    }
}
