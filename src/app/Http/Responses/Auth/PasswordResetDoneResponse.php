<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\PasswordResetResponse;

class PasswordResetDoneResponse implements PasswordResetResponse
{
    public const MESSAGE = 'Your password is changed and every other session is signed out. Sign in with the new password.';

    public function __construct(public readonly string $status = '') {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        return redirect()->route('login')->with('status', self::MESSAGE);
    }
}
