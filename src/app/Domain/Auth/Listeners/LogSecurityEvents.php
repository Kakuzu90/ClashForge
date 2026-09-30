<?php

namespace App\Domain\Auth\Listeners;

use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Sign-ins, sign-outs and password resets go to the `security` channel (specs/11 §3): the user's
 * ULID and a hashed IP, never the email or anything typed into the form. Failed sign-ins are
 * logged by AuthenticationService, which knows the account.
 */
class LogSecurityEvents
{
    public function login(Login $event): void
    {
        $this->log('auth.login', $event->user, 'info', ['remember' => $event->remember]);
    }

    public function logout(Logout $event): void
    {
        $this->log('auth.logout', $event->user, 'info');
    }

    public function passwordReset(PasswordReset $event): void
    {
        $this->log('auth.password_reset', $event->user, 'info');
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'login',
            Logout::class => 'logout',
            PasswordReset::class => 'passwordReset',
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function log(string $message, mixed $user, string $level, array $extra = []): void
    {
        Log::channel('security')->log($level, $message, [
            'user' => $user instanceof User ? $user->ulid : null,
            'ip_hash' => IpHash::of(request()->ip()),
            ...$extra,
        ]);
    }
}
