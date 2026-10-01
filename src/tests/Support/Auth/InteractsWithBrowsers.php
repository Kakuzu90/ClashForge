<?php

namespace Tests\Support\Auth;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Real browser sessions for the session-management tests: the database session driver, a sign-in
 * through /login, and the cookies it set replayed on later requests, one set per "browser".
 */
trait InteractsWithBrowsers
{
    protected function useDatabaseSessions(): void
    {
        // The specs/04 §4 idle limit (14 days), whatever the local .env sets.
        config(['session.driver' => 'database', 'session.lifetime' => 20160]);
    }

    /**
     * The request Inertia's client sends for a deferred prop, from this browser.
     *
     * @param  array<string, string>  $cookies
     */
    protected function loadDeferred(array $cookies, string $url, string $component, string $props): TestResponse
    {
        $version = (string) app(HandleInertiaRequests::class)->version(request());

        return $this->browser($cookies, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => $component,
            'X-Inertia-Partial-Data' => $props,
        ])->get($url);
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, string>  $cookies  cookies the browser already holds (e.g. known_devices)
     * @return array<string, string> the browser's cookies after signing in
     */
    protected function signInBrowser(User $user, array $headers = [], bool $remember = false, array $cookies = [], string $password = 'password'): array
    {
        $response = $this->browser($cookies, $headers)->post('/login', ['email' => $user->email, 'password' => $password, 'remember' => $remember]);
        $response->assertRedirect();

        return [...$cookies, ...self::cookiesFrom($response)];
    }

    /**
     * A clean client carrying only these cookies and headers.
     *
     * @param  array<string, string>  $cookies
     * @param  array<string, string>  $headers
     */
    protected function browser(array $cookies, array $headers = []): static
    {
        $this->freshRequestState();

        foreach ($cookies as $name => $value) {
            // The values are already encrypted by the app; send them as the browser would.
            $this->withUnencryptedCookie($name, $value);
        }

        return $this->withHeaders($headers);
    }

    /**
     * @return array<string, string>
     */
    protected static function cookiesFrom(TestResponse $response): array
    {
        $cookies = [];

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getValue() !== null && $cookie->getExpiresTime() !== 1 && ! $cookie->isCleared()) {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }
        }

        return $cookies;
    }

    /**
     * In production every request gets a new session store, guard and cookie jar; the test app
     * keeps them, so one browser's session would leak into the next request.
     */
    protected function freshRequestState(): void
    {
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];

        $this->app->make('session')->forgetDrivers();
        $this->app->forgetInstance('session.store');
        // The redirector holds the session store it was created with, for flash data.
        $this->app->forgetInstance('redirect');
        $this->app->make('cookie')->flushQueuedCookies();
        $this->app->make('auth')->forgetGuards();
        // `auth.driver` (the Guard contract the session handler asks for its user id) is a singleton.
        $this->app->forgetInstance('auth.driver');

        // Routes cache their controller, and Fortify's take the guard in the constructor.
        foreach ($this->app->make('router')->getRoutes() as $route) {
            $route->controller = null;
        }
    }
}
