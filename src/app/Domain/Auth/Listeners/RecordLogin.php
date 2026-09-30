<?php

namespace App\Domain\Auth\Listeners;

use App\Domain\Auth\Services\AuthenticationService;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Stamps `last_login_at` and the hashed IP on every login, remember-me logins included. Runs in
 * the request because the IP is only known there; it is one narrow update.
 */
class RecordLogin
{
    public function __construct(
        private readonly AuthenticationService $auth,
        private readonly Request $request,
    ) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->auth->recordLogin($event->user, $this->request->ip());
        }
    }
}
