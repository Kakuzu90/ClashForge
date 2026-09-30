<?php

namespace App\Http\Responses\Auth;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * One answer whether or not the email has an account (specs/11 "Account enumeration"): the
 * existence signal only ever goes to the inbox.
 */
class PasswordResetLinkResponse implements Responsable
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        return back()->with('status', self::message());
    }

    public static function message(): string
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return "If an account uses that email, a link to reset its password is on its way. It expires in {$minutes} minutes.";
    }
}
