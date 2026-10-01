<?php

namespace App\Providers;

use App\Domain\Auth\Exceptions\AccountBanned;
use App\Domain\Auth\Services\AuthenticationService;
use App\Domain\Auth\Services\PasswordResetService;
use App\Http\Data\Auth\ConfirmPasswordPageData;
use App\Http\Data\Auth\ForgotPasswordPageData;
use App\Http\Data\Auth\LoginPageData;
use App\Http\Data\Auth\ResetPasswordPageData;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Responses\Auth\PasswordConfirmationFailedResponse;
use App\Http\Responses\Auth\PasswordResetDoneResponse;
use App\Http\Responses\Auth\PasswordResetFailedResponse;
use App\Support\Seo\PageMeta;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Laravel\Fortify\Contracts\FailedPasswordConfirmationResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

/**
 * Fortify runs the auth backend; the pages, copy and routes are ours (specs/06 "Auth scaffolding").
 * Routes live in routes/web/auth.php so their limiters sit next to them.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();

        // Fortify's controller type-hints its own request; ours adds the email format check.
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);

        $this->app->bind(FailedPasswordResetResponse::class, PasswordResetFailedResponse::class);
        $this->app->bind(PasswordResetResponse::class, PasswordResetDoneResponse::class);
        $this->app->bind(FailedPasswordConfirmationResponse::class, PasswordConfirmationFailedResponse::class);
    }

    public function boot(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            try {
                return app(AuthenticationService::class)->attempt(
                    $request->string('email')->toString(),
                    $request->string('password')->toString(),
                );
            } catch (AccountBanned $e) {
                throw ValidationException::withMessages([
                    'email' => $e->reason === null ? __('auth.banned') : __('auth.banned_reason', ['reason' => $e->reason]),
                ]);
            }
        });

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

        Fortify::confirmPasswordView(fn (): Response => PageMeta::page(
            'Auth/ConfirmPassword',
            (new ConfirmPasswordPageData(minutes: intdiv((int) config('auth.password_timeout'), 60)))->toArray(),
            new PageMeta(title: 'Confirm your password', noindex: true),
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
