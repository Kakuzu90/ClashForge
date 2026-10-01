<?php

namespace App\Domain\Auth\Listeners;

use App\Domain\Auth\Events\UnrecognisedDeviceSignedIn;
use App\Domain\Auth\Notifications\NewSignInNotification;
use App\Domain\Auth\Services\SessionService;
use App\Domain\Auth\Support\CountryName;
use App\Domain\Auth\Support\DeviceLabel;
use App\Domain\Auth\Support\KnownDevices;
use App\Domain\Auth\Support\RememberOrigin;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

/**
 * On every sign-in: starts the session's absolute-lifetime clock (specs/04 §4) and emails the
 * owner when this browser has not signed in to the account before (specs/11, specs/16 §2). An
 * account's very first sign-in gets a "first sign-in" email instead (registration never signs in,
 * so this is the first moment anyone used the password), and no in-app copy. A
 * sign-in from a remember-me cookie inherits the time of the password sign-in behind it, so
 * remember-me never stretches the 30 days. Runs in the request because the cookies and the
 * session live there; the email is queued.
 */
class TrackSignIn
{
    public function __construct(
        private readonly Request $request,
        private readonly AuthFactory $auth,
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->startClock($event);
        $this->checkDevice($user);
    }

    private function startClock(Login $event): void
    {
        $now = Date::now()->getTimestamp();
        $guard = $this->auth->guard($event->guard);
        $viaRemember = method_exists($guard, 'viaRemember') && $guard->viaRemember();

        // No origin cookie: 0, so EnforceAbsoluteSessionLifetime ends the session at once.
        $signedInAt = $viaRemember ? (RememberOrigin::of($this->request) ?? 0) : $now;

        if (! $viaRemember && $event->remember) {
            RememberOrigin::start($now);
        }

        if ($this->request->hasSession()) {
            $this->request->session()->put(SessionService::SIGNED_IN_AT, $signedInAt);
        }
    }

    private function checkDevice(User $user): void
    {
        if (! KnownDevices::knows($this->request, $user->ulid)) {
            $device = DeviceLabel::fromUserAgent($this->request->userAgent());
            $country = CountryName::of(CountryName::fromRequest($this->request));
            $first = $user->last_login_at === null;

            Log::channel('security')->info('auth.new_device', ['user' => $user->ulid, 'ip_hash' => IpHash::of($this->request->ip()), 'device' => $device, 'first' => $first]);
            $user->notify(new NewSignInNotification($device, $country, Date::now()->toImmutable(), $first));

            if (! $first) {
                UnrecognisedDeviceSignedIn::dispatch($user->id, $device, $country);
            }
        }

        KnownDevices::remember($this->request, $user->ulid);
    }
}
